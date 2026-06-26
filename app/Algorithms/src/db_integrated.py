import random
import sys
import os
import time
import json
import select
from copy import deepcopy
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from src.predict import predict_demand

# =====================================================================
# 1. KONSTANTA DEFAULT
# =====================================================================
_DEFAULT_GA = {
    "population_size":     200,
    "generations":         1000,
    "crossover_rate":      0.80,
    "mutation_rate":       0.10,
    "elitism_count":       20,
    "min_driver_rest_min": 30,    # EU Directive 2002/15/EC
}

_DEFAULT_BUS_CAPACITY   = 80     # Dimensions.com + TRB
_DEFAULT_MAX_HOURS      = 8      # ILO Convention + EU EC 561/2006
_REST_AFTER_HOURS       = 6      # Istirahat wajib setelah 6 jam kerja
_REST_DURATION_MIN      = 30     # 30 menit istirahat (EU Directive 2002/15/EC)
_RESERVE_PCT            = 0.15   # 15% armada reserve (Arterials Transit Planning)
_MAINTENANCE_WINDOW_MIN = 120    # 2 jam/bus maintenance window (TCRP Synthesis 81)
_OPS_START              = "05:00"
_OPS_END                = "23:00"
_OPS_TOTAL_MIN          = 18 * 60  # 05:00-23:00 = 18 jam operasional
_BUS_MAX_DAILY_MIN      = _OPS_TOTAL_MIN - _MAINTENANCE_WINDOW_MIN  # 16 jam max operasi

_TOURNAMENT_K           = 5
_HEADWAY_MIN            = 5
_HEADWAY_MAX            = 30
_STAGNASI_MUTASI        = 25
_STAGNASI_RESTART       = 60
_DIVERSITY_INJECT_PCT   = 0.30
_DEFAULT_KM_THRESHOLD   = 10000   # fallback jika maintenance_thresholds tidak ada di payload

# [OPSIONAL-1] Kecepatan transfer bus antar terminal (dalam kota, km/jam)
_BUS_TRANSFER_SPEED_KMH = 30.0


# =====================================================================
# 2. HELPERS WAKTU
# =====================================================================
def _to_minutes(time_str) -> int:
    if not time_str:
        return 0
    parts = str(time_str).split(":")
    try:
        return int(parts[0]) * 60 + int(parts[1])
    except (IndexError, ValueError):
        return 0


def _to_time_str(minutes: int) -> str:
    minutes = max(0, int(minutes))
    h = (minutes // 60) % 24
    m = minutes % 60
    return f"{h:02d}:{m:02d}:00"


def _progress(msg: str):
    print(msg, file=sys.stderr, flush=True)


# [WAJIB-3] Konsistenkan nama kolom: prioritas distance_from_prev_stop (schema DB baru).
# Fallback ke distance_from_prev_halte hanya untuk kompatibilitas backward schema lama.
def _get_dist_halte(rs: dict) -> float:
    return float(
        rs.get("distance_from_prev_stop") or   # ← schema DB baru (PRIORITAS)
        rs.get("distance_from_prev_halte") or  # ← schema lama (fallback backward compat)
        0
    )


# =====================================================================
# 3. VALIDASI PAYLOAD
# =====================================================================
def _validasi_payload(payload: dict) -> list:
    errors = []
    for key in ["routes", "buses", "drivers", "conductors",
                "route_stops"]:
        if key not in payload:
            errors.append(f"Field wajib '{key}' tidak ada di payload.")
        elif not isinstance(payload[key], list):
            errors.append(f"Field '{key}' harus berupa list.")
    if not errors:
        for key in ["routes", "buses", "drivers", "conductors",
                    "route_stops"]:
            if not payload[key]:
                errors.append(f"'{key}' tidak boleh kosong.")
    return errors


# =====================================================================
# [OPSIONAL-1] HELPER PENALTI LOKASI BUS
# =====================================================================
def _hitung_jarak_stop(stop_id_a, stop_id_b, stop_coords: dict) -> float:
    """
    Hitung jarak Euclidean antara dua stop berdasarkan koordinat (lat/lng).
    Jika koordinat tidak tersedia, return 0 (tidak memberi penalti).
    stop_coords: { stop_id: {"lat": float, "lng": float} }
    """
    if stop_id_a is None or stop_id_b is None:
        return 0.0
    if stop_id_a == stop_id_b:
        return 0.0
    coord_a = stop_coords.get(stop_id_a)
    coord_b = stop_coords.get(stop_id_b)
    if not coord_a or not coord_b:
        return 0.0
    # Haversine sederhana (approx untuk kota, akurasi cukup)
    import math
    lat1, lon1 = math.radians(coord_a["lat"]), math.radians(coord_a["lng"])
    lat2, lon2 = math.radians(coord_b["lat"]), math.radians(coord_b["lng"])
    dlat = lat2 - lat1
    dlon = lon2 - lon1
    a = math.sin(dlat/2)**2 + math.cos(lat1) * math.cos(lat2) * math.sin(dlon/2)**2
    c = 2 * math.asin(math.sqrt(a))
    return 6371 * c  # km


# =====================================================================
# 4. FUNGSI EVALUASI / FITNESS
# =====================================================================
def _evaluasi_kromosom(kromosom: list, ctx: dict) -> float:
    penalti = 0.0

    rute_map        = ctx["rute_map"]
    bus_map         = ctx["bus_map"]
    driver_map      = ctx["driver_map"]
    cond_map        = ctx["cond_map"]
    threshold_map   = ctx["threshold_map"]
    prediksi_map    = ctx["prediksi_map"]
    min_rest        = ctx["min_rest"]
    total_bus       = ctx["total_bus"]
    reserve_count   = ctx["reserve_count"]
    stop_coords     = ctx.get("stop_coords", {})   # [OPSIONAL-1]

    bus_free_at         = {}
    bus_daily_min       = {}
    # [OPSIONAL-1] Track posisi terakhir bus (destination_stop_id dari rute terakhir)
    bus_last_stop       = {}
    driver_free_at      = {}
    driver_worked       = {}
    driver_continuous   = {}
    cond_free_at        = {}
    cond_worked         = {}
    cond_continuous     = {}
    last_dep_per_route  = {}
    kapasitas_per_jam   = {}
    bus_in_use_at_hour  = {}

    for trip in sorted(kromosom, key=lambda t: t.get("departure_min", 0)):
        dep_min   = trip.get("departure_min", 0)
        route_id  = trip.get("route_id")
        bus_id    = trip.get("bus_id")
        driver_id = trip.get("driver_id")
        cond_id   = trip.get("conductor_id")

        # P3: integritas
        if None in (route_id, bus_id, driver_id, cond_id):
            penalti += 5000
            continue
        rute   = rute_map.get(route_id)
        bus    = bus_map.get(bus_id)
        driver = driver_map.get(driver_id)
        cond   = cond_map.get(cond_id)
        if not all([rute, bus, driver, cond]):
            penalti += 5000
            continue

        travel_min = rute.get("estimated_travel_time_min", 60) or 60
        arr_min    = dep_min + travel_min
        dep_hour   = dep_min // 60

        # P1: Headway
        if route_id in last_dep_per_route:
            hw = dep_min - last_dep_per_route[route_id]
            if hw < _HEADWAY_MIN:
                penalti += (_HEADWAY_MIN - hw) * 500
            elif hw > _HEADWAY_MAX:
                penalti += (hw - _HEADWAY_MAX) * 500
        last_dep_per_route[route_id] = dep_min

        # Akumulasi kapasitas per jam
        key = (route_id, dep_hour)
        kapasitas_per_jam[key] = (
            kapasitas_per_jam.get(key, 0) + (bus.get("capacity") or _DEFAULT_BUS_CAPACITY)
        )

        # Track bus per jam untuk reserve check (P9)
        for h in range(dep_hour, (arr_min // 60) + 1):
            if h not in bus_in_use_at_hour:
                bus_in_use_at_hour[h] = set()
            bus_in_use_at_hour[h].add(bus_id)

        # P4 + P5 + P8_waktu: kru — shift, overtime, dan istirahat 6 jam
        for person, fa_map, wk_map, cont_map in [
            (driver, driver_free_at, driver_worked, driver_continuous),
            (cond,   cond_free_at,   cond_worked,   cond_continuous),
        ]:
            pid        = person.get("id")
            if not pid:
                continue
            if person.get("is_leave") or person.get("status") == "cuti":
                penalti += 5000
                continue  

            shift_s    = _to_minutes(person.get("shift_start", _OPS_START))
            shift_e    = _to_minutes(person.get("shift_end",   _OPS_END))
            max_min    = (person.get("max_hours") or _DEFAULT_MAX_HOURS) * 60
            break_dur  = person.get("break_duration") or min_rest

            # P4: di luar shift
            if dep_min < shift_s or dep_min > shift_e:
                penalti += 2000

            # P5: overtime
            total_kerja = wk_map.get(pid, 0) + travel_min
            if total_kerja > max_min:
                penalti += ((total_kerja - max_min) // 15) * 1500
            wk_map[pid] = total_kerja

            # P5b: istirahat minimum antar trip
            if pid in fa_map and dep_min < fa_map[pid] + break_dur:
                penalti += 1500

            # P8_waktu: istirahat 30 menit setelah 6 jam kerja berkesinambungan
            prev_arr   = fa_map.get(pid, 0)
            gap        = dep_min - prev_arr if pid in fa_map else 999
            if gap >= break_dur:
                cont_map[pid] = travel_min
            else:
                cont_map[pid] = cont_map.get(pid, 0) + travel_min

            if cont_map[pid] > _REST_AFTER_HOURS * 60:
                penalti += 2000

            fa_map[pid] = arr_min

        # [OPSIONAL-2] P6: km threshold — WAJIB baca dari threshold_map (fix dari v4)
        # v4 bug: threshold_map ada di ctx tapi P6 tidak memakainya, masih hardcode 5000
        if bus_id in threshold_map:
            km_threshold = threshold_map[bus_id].get("km_threshold", _DEFAULT_KM_THRESHOLD)
        else:
            km_threshold = _DEFAULT_KM_THRESHOLD  # fallback jika bus tidak ada di threshold_map
        km = (bus.get("total_distance") or 0) + (rute.get("total_distance_km") or 0)
        if km >= km_threshold:
            penalti += 3000

        # P7: anti-teleportasi bus (waktu)
        if bus_id in bus_free_at and dep_min < bus_free_at[bus_id]:
            penalti += 2500

        # [OPSIONAL-1] P8_lokasi: penalti lokasi bus
        # Bus selesai di destination_stop rute sebelumnya, lalu diassign ke rute baru
        # yang origin_stop-nya berbeda. Cek apakah waktu tempuh transfer cukup.
        origin_stop  = rute.get("origin_stop_id")
        dest_stop    = rute.get("destination_stop_id")
        if bus_id in bus_last_stop and origin_stop is not None:
            prev_dest = bus_last_stop[bus_id]
            if prev_dest != origin_stop:
                jarak_transfer = _hitung_jarak_stop(prev_dest, origin_stop, stop_coords)
                if jarak_transfer > 0:
                    # Waktu minimum transfer yang dibutuhkan (menit)
                    min_transfer_min = (jarak_transfer / _BUS_TRANSFER_SPEED_KMH) * 60
                    waktu_tersedia   = dep_min - bus_free_at.get(bus_id, dep_min)
                    if waktu_tersedia < min_transfer_min:
                        # Bus tidak mungkin sampai tepat waktu secara fisik
                        penalti += (min_transfer_min - waktu_tersedia) * 50

        bus_free_at[bus_id]    = arr_min
        bus_last_stop[bus_id]  = dest_stop  # [OPSIONAL-1] update posisi terakhir bus
        bus_daily_min[bus_id]  = bus_daily_min.get(bus_id, 0) + travel_min

        # P10: window maintenance
        if bus_daily_min[bus_id] > _BUS_MAX_DAILY_MIN:
            penalti += (bus_daily_min[bus_id] - _BUS_MAX_DAILY_MIN) * 10

    # P2: kapasitas vs demand RF
    for (rid, hour), total_kap in kapasitas_per_jam.items():
        demand = prediksi_map.get((rid, hour), 0)
        if total_kap < demand:
            penalti += (demand - total_kap) * 50
        elif total_kap > demand * 1.5:
            kelebihan_kursi = total_kap - int(demand * 1.5)
            # [Tuning] Jika jam sepi (demand < 30), diskon denda 80% (dari 5 jadi 1 poin)
            if demand < 20:
                penalti += kelebihan_kursi * 1
            else:
                penalti += kelebihan_kursi * 5

    # P9: reserve armada
    for hour, bus_set in bus_in_use_at_hour.items():
        dipakai = len(bus_set)
        if dipakai > (total_bus - reserve_count):
            penalti += (dipakai - (total_bus - reserve_count)) * 1000

    return -penalti


# =====================================================================
# 5. LEGAL CONSTRUCTOR
# =====================================================================
def _kru_aktif_pada(kru_list: list, dep_min: int) -> list:
    aktif = [
        k for k in kru_list
        if _to_minutes(k.get("shift_start", _OPS_START)) <= dep_min
        <= _to_minutes(k.get("shift_end",   _OPS_END))
    ]
    return aktif if aktif else kru_list


def _buat_satu_kromosom_legal(payload: dict, bus_aktif: list,
                               driver_aktif: list, cond_aktif: list) -> list:
    # [WAJIB-1] Hanya pakai bus_aktif (sudah difilter status == "active")
    # [WAJIB-2] Hanya pakai driver_aktif dan cond_aktif (sudah exclude yang cuti)
    bus_ids        = [b["id"] for b in bus_aktif]
    bus_free_at    = {bid: 0 for bid in bus_ids}
    bus_daily_min  = {bid: 0 for bid in bus_ids}
    driver_free_at = {d["id"]: 0 for d in driver_aktif}
    driver_worked  = {d["id"]: 0 for d in driver_aktif}
    driver_cont    = {d["id"]: 0 for d in driver_aktif}
    cond_free_at   = {c["id"]: 0 for c in cond_aktif}
    cond_worked    = {c["id"]: 0 for c in cond_aktif}
    cond_cont      = {c["id"]: 0 for c in cond_aktif}

    total_bus     = len(bus_ids)
    reserve_cnt   = max(1, int(total_bus * _RESERVE_PCT))
    max_bus_aktif = total_bus - reserve_cnt

    min_rest = payload.get("_min_rest_internal", _DEFAULT_GA["min_driver_rest_min"])
    kromosom = []

    for rute in payload["routes"]:
        time_start = _to_minutes(rute.get("time_start", _OPS_START))
        time_end   = _to_minutes(rute.get("time_end",   _OPS_END))
        travel_min = rute.get("estimated_travel_time_min", 60) or 60
        route_id   = rute["id"]
        max_hours  = _DEFAULT_MAX_HOURS * 60

        dep = time_start
        while dep + travel_min <= time_end:
            arr = dep + travel_min

            bus_bebas = [
                bid for bid in bus_ids
                if bus_free_at[bid] <= dep
                and bus_daily_min[bid] + travel_min <= _BUS_MAX_DAILY_MIN
            ]
            bus_aktif_sekarang = len([bid for bid in bus_ids if bus_free_at[bid] > dep])
            if bus_aktif_sekarang >= max_bus_aktif:
                bus_bebas = []

            if not bus_bebas:
                dep = min((bus_free_at[bid] for bid in bus_ids), default=dep + _HEADWAY_MIN)
                if dep + travel_min > time_end:
                    break
                bus_bebas = [
                    bid for bid in bus_ids
                    if bus_free_at[bid] <= dep
                    and bus_daily_min[bid] + travel_min <= _BUS_MAX_DAILY_MIN
                ]
                if not bus_bebas:
                    break

            bus_id = random.choice(bus_bebas)

            def driver_ok(d):
                pid    = d["id"]
                br     = d.get("break_duration") or min_rest
                sh_s   = _to_minutes(d.get("shift_start", _OPS_START))
                sh_e   = _to_minutes(d.get("shift_end",   _OPS_END))
                worked = driver_worked.get(pid, 0)
                free   = driver_free_at.get(pid, 0)
                cont   = driver_cont.get(pid, 0)
                gap    = dep - free if pid in driver_free_at else 999
                cont_after = travel_min if gap >= br else cont + travel_min
                return (
                    sh_s <= dep <= sh_e
                    and dep >= free + br
                    and worked + travel_min <= max_hours
                    and cont_after <= _REST_AFTER_HOURS * 60
                )

            driver_pool = [d for d in driver_aktif if driver_ok(d)]
            if not driver_pool:
                driver_pool = _kru_aktif_pada(driver_aktif, dep)
            driver    = random.choice(driver_pool)
            driver_id = driver["id"]

            def cond_ok(c):
                pid    = c["id"]
                br     = c.get("break_duration") or min_rest
                sh_s   = _to_minutes(c.get("shift_start", _OPS_START))
                sh_e   = _to_minutes(c.get("shift_end",   _OPS_END))
                worked = cond_worked.get(pid, 0)
                free   = cond_free_at.get(pid, 0)
                cont   = cond_cont.get(pid, 0)
                gap    = dep - free if pid in cond_free_at else 999
                cont_after = travel_min if gap >= br else cont + travel_min
                return (
                    sh_s <= dep <= sh_e
                    and dep >= free + br
                    and worked + travel_min <= max_hours
                    and cont_after <= _REST_AFTER_HOURS * 60
                )

            cond_pool = [c for c in cond_aktif if cond_ok(c)]
            if not cond_pool:
                cond_pool = _kru_aktif_pada(cond_aktif, dep)
            cond    = random.choice(cond_pool)
            cond_id = cond["id"]

            kromosom.append({
                "departure_min": dep,
                "route_id":      route_id,
                "bus_id":        bus_id,
                "driver_id":     driver_id,
                "conductor_id":  cond_id,
            })

            gap_d = dep - driver_free_at.get(driver_id, 0)
            driver_cont[driver_id] = travel_min if gap_d >= (driver.get("break_duration") or min_rest) else driver_cont.get(driver_id, 0) + travel_min
            gap_c = dep - cond_free_at.get(cond_id, 0)
            cond_cont[cond_id] = travel_min if gap_c >= (cond.get("break_duration") or min_rest) else cond_cont.get(cond_id, 0) + travel_min

            bus_free_at[bus_id]       = arr
            bus_daily_min[bus_id]     = bus_daily_min.get(bus_id, 0) + travel_min
            driver_free_at[driver_id] = arr
            driver_worked[driver_id]  = driver_worked.get(driver_id, 0) + travel_min
            cond_free_at[cond_id]     = arr
            cond_worked[cond_id]      = cond_worked.get(cond_id, 0) + travel_min

            dep += random.randint(_HEADWAY_MIN, _HEADWAY_MAX)

    return kromosom


def _buat_populasi_awal(payload: dict, pop_size: int,
                        bus_aktif: list, driver_aktif: list, cond_aktif: list) -> list:
    return [_buat_satu_kromosom_legal(payload, bus_aktif, driver_aktif, cond_aktif)
            for _ in range(pop_size)]


# =====================================================================
# 6. REPAIR OPERATOR
# =====================================================================
def _repair(kromosom: list, payload: dict, min_rest: int,
            bus_aktif: list, driver_aktif: list, cond_aktif: list) -> list:
    # [WAJIB-1] [WAJIB-2] Gunakan bus/kru yang sudah difilter
    bus_ids    = [b["id"] for b in bus_aktif]
    rute_map   = {r["id"]: r for r in payload["routes"]}
    driver_map = {d["id"]: d for d in driver_aktif}
    cond_map   = {c["id"]: c for c in cond_aktif}

    total_bus     = len(bus_ids)
    reserve_cnt   = max(1, int(total_bus * _RESERVE_PCT))
    max_bus_aktif = total_bus - reserve_cnt

    bus_free_at    = {}
    bus_daily_min  = {}
    driver_free_at = {}
    cond_free_at   = {}

    for trip in sorted(kromosom, key=lambda t: t.get("departure_min", 0)):
        dep_min  = trip.get("departure_min", 0)
        route_id = trip.get("route_id")
        rute     = rute_map.get(route_id, {})
        travel   = rute.get("estimated_travel_time_min", 60) or 60
        arr      = dep_min + travel

        bid = trip.get("bus_id")
        # [WAJIB-1] Cek bus masih di pool aktif
        if bid not in bus_ids:
            bid = None
        if bid and (bus_free_at.get(bid, 0) > dep_min or
                    bus_daily_min.get(bid, 0) + travel > _BUS_MAX_DAILY_MIN):
            alt = [
                b for b in bus_ids
                if bus_free_at.get(b, 0) <= dep_min
                and bus_daily_min.get(b, 0) + travel <= _BUS_MAX_DAILY_MIN
            ]
            if alt:
                bid = random.choice(alt)
                trip["bus_id"] = bid
        if bid:
            bus_free_at[bid]   = arr
            bus_daily_min[bid] = bus_daily_min.get(bid, 0) + travel

        did = trip.get("driver_id")
        # [WAJIB-2] Cek driver masih di pool aktif
        if did not in driver_map:
            did = None
        if did:
            drv = driver_map.get(did, {})
            br  = drv.get("break_duration", min_rest)
            if driver_free_at.get(did, 0) + br > dep_min:
                pool = [
                    d for d in driver_aktif
                    if driver_free_at.get(d["id"], 0) + (d.get("break_duration") or min_rest) <= dep_min
                    and _to_minutes(d.get("shift_start", _OPS_START)) <= dep_min
                    <= _to_minutes(d.get("shift_end", _OPS_END))
                ]
                if pool:
                    did = random.choice(pool)["id"]
                    trip["driver_id"] = did
        if did:
            driver_free_at[did] = arr

        cid = trip.get("conductor_id")
        # [WAJIB-2] Cek kondektur masih di pool aktif
        if cid not in cond_map:
            cid = None
        if cid:
            br = (cond_map.get(cid) or {}).get("break_duration", min_rest)
            if cond_free_at.get(cid, 0) + br > dep_min:
                pool = [
                    c for c in cond_aktif
                    if cond_free_at.get(c["id"], 0) + (c.get("break_duration") or min_rest) <= dep_min
                    and _to_minutes(c.get("shift_start", _OPS_START)) <= dep_min
                    <= _to_minutes(c.get("shift_end", _OPS_END))
                ]
                if pool:
                    cid = random.choice(pool)["id"]
                    trip["conductor_id"] = cid
        if cid:
            cond_free_at[cid] = arr

    return sorted(kromosom, key=lambda t: t.get("departure_min", 0))


# =====================================================================
# 7. OPERATOR EVOLUSI
# =====================================================================
def _tournament(skor_pop: list, k: int = _TOURNAMENT_K) -> list:
    return max(random.sample(skor_pop, min(k, len(skor_pop))), key=lambda x: x[1])[0]


def _crossover(induk1: list, induk2: list, cr: float, payload: dict, min_rest: int,
               bus_aktif: list, driver_aktif: list, cond_aktif: list):
    if random.random() > cr:
        return [t.copy() for t in induk1], [t.copy() for t in induk2]
    n = min(len(induk1), len(induk2))
    if n < 3:
        return [t.copy() for t in induk1], [t.copy() for t in induk2]
    t1 = random.randint(1, n // 3)
    t2 = random.randint(n // 3 + 1, n - 1)
    a1 = [t.copy() for t in induk1[:t1]] + [t.copy() for t in induk2[t1:t2]] + [t.copy() for t in induk1[t2:]]
    a2 = [t.copy() for t in induk2[:t1]] + [t.copy() for t in induk1[t1:t2]] + [t.copy() for t in induk2[t2:]]
    return (_repair(a1, payload, min_rest, bus_aktif, driver_aktif, cond_aktif),
            _repair(a2, payload, min_rest, bus_aktif, driver_aktif, cond_aktif))


def _mutasi(kromosom: list, payload: dict, mutation_rate: float, min_rest: int,
            bus_aktif: list, driver_aktif: list, cond_aktif: list) -> list:
    # [WAJIB-1] [WAJIB-2] Gunakan bus/kru yang sudah difilter
    bus_ids  = [b["id"] for b in bus_aktif]
    rute_map = {r["id"]: r for r in payload["routes"]}

    bus_free_at    = {}
    bus_daily_min  = {}
    driver_free_at = {}
    cond_free_at   = {}
    for trip in sorted(kromosom, key=lambda t: t.get("departure_min", 0)):
        dep  = trip.get("departure_min", 0)
        rid  = trip.get("route_id")
        trav = (rute_map.get(rid) or {}).get("estimated_travel_time_min", 60) or 60
        arr  = dep + trav
        for fmap, key in [(bus_free_at, "bus_id"), (driver_free_at, "driver_id"), (cond_free_at, "conductor_id")]:
            eid = trip.get(key)
            if eid:
                fmap[eid] = arr
        bid = trip.get("bus_id")
        if bid:
            bus_daily_min[bid] = bus_daily_min.get(bid, 0) + trav

    for i, trip in enumerate(kromosom):
        if random.random() >= mutation_rate:
            continue
        dep_min = trip.get("departure_min", 0)
        gen_mana = random.choices(
            ["swap_bus", "swap_driver", "swap_conductor", "shift_dep", "swap_crew_trips"],
            weights=[1, 2, 2, 2, 1], k=1
        )[0]

        if gen_mana == "swap_bus":
            bebas = [
                b for b in bus_ids
                if bus_free_at.get(b, 0) <= dep_min
                and bus_daily_min.get(b, 0) + (rute_map.get(trip.get("route_id"), {}).get("estimated_travel_time_min", 60) or 60) <= _BUS_MAX_DAILY_MIN
            ]
            if bebas:
                trip["bus_id"] = random.choice(bebas)

        elif gen_mana == "swap_driver":
            pool = [
                d for d in driver_aktif
                if driver_free_at.get(d["id"], 0) + (d.get("break_duration") or min_rest) <= dep_min
                and _to_minutes(d.get("shift_start", _OPS_START)) <= dep_min
                <= _to_minutes(d.get("shift_end", _OPS_END))
            ]
            if pool:
                trip["driver_id"] = random.choice(pool)["id"]

        elif gen_mana == "swap_conductor":
            pool = [
                c for c in cond_aktif
                if cond_free_at.get(c["id"], 0) + (c.get("break_duration") or min_rest) <= dep_min
                and _to_minutes(c.get("shift_start", _OPS_START)) <= dep_min
                <= _to_minutes(c.get("shift_end", _OPS_END))
            ]
            if pool:
                trip["conductor_id"] = random.choice(pool)["id"]

        elif gen_mana == "shift_dep":
            rute       = rute_map.get(trip.get("route_id"), {})
            time_start = _to_minutes(rute.get("time_start", _OPS_START))
            time_end   = _to_minutes(rute.get("time_end",   _OPS_END))
            travel_min = rute.get("estimated_travel_time_min", 60) or 60
            new_dep    = dep_min + random.randint(-5, 5)
            trip["departure_min"] = max(time_start, min(new_dep, time_end - travel_min))

        elif gen_mana == "swap_crew_trips" and len(kromosom) > 1:
            j = random.randint(0, len(kromosom) - 1)
            if i != j:
                ti, tj   = kromosom[i], kromosom[j]
                dep_i, dep_j = ti.get("departure_min", 0), tj.get("departure_min", 0)
                def in_shift(person_list, pid, dep):
                    p = next((x for x in person_list if x["id"] == pid), None)
                    if not p:
                        return False
                    return (_to_minutes(p.get("shift_start", _OPS_START)) <= dep
                            <= _to_minutes(p.get("shift_end", _OPS_END)))
                if (in_shift(driver_aktif, ti["driver_id"],    dep_j) and
                    in_shift(driver_aktif, tj["driver_id"],    dep_i) and
                    in_shift(cond_aktif,   ti["conductor_id"], dep_j) and
                    in_shift(cond_aktif,   tj["conductor_id"], dep_i)):
                    ti["driver_id"],    tj["driver_id"]    = tj["driver_id"],    ti["driver_id"]
                    ti["conductor_id"], tj["conductor_id"] = tj["conductor_id"], ti["conductor_id"]

    return kromosom


# =====================================================================
# 8. HITUNG ESTIMASI WAKTU TIBA PER HALTE
# =====================================================================
def _hitung_stop_arrivals(dep_min: int, route_id: int, route_stops_map: dict) -> list:
    stops = route_stops_map.get(route_id, [])
    hasil = []
    waktu = dep_min
    for stop in stops:
        waktu += stop.get("estimated_travel_time_min", 0)
        hasil.append({
            "sequence":          stop.get("sequence"),
            "stop_id":           stop.get("stop_id"),
            "stop_name":         stop.get("stop_name", f"Halte {stop.get('stop_id')}"),
            "estimated_arrival": _to_time_str(waktu),
        })
    return hasil


# =====================================================================
# 9. MESIN UTAMA GA
# =====================================================================
def jalankan_optimasi(payload: dict) -> dict:
    errors = _validasi_payload(payload)
    if errors:
        return {"status": "error", "errors": errors}

    ga_cfg         = payload.get("ga_parameters") or {}
    pop_size       = int(ga_cfg.get("population_size")     or _DEFAULT_GA["population_size"])
    generations    = int(ga_cfg.get("generations")         or _DEFAULT_GA["generations"])
    crossover_rate = float(ga_cfg.get("crossover_rate")    or _DEFAULT_GA["crossover_rate"])
    mutation_rate  = float(ga_cfg.get("mutation_rate")     or _DEFAULT_GA["mutation_rate"])
    elitism_count  = min(int(ga_cfg.get("elitism_count")   or _DEFAULT_GA["elitism_count"]), pop_size)
    min_rest       = int(ga_cfg.get("min_driver_rest_min") or _DEFAULT_GA["min_driver_rest_min"])

    # Simpan min_rest ke payload untuk diakses oleh _buat_satu_kromosom_legal
    payload["_min_rest_internal"] = min_rest

    # === PREPROCESSING: assign shift otomatis (split shift 2 sesi/hari) ===
    def assign_shifts(kru_list, min_break):
        n = len(kru_list)
        for i, kru in enumerate(kru_list):
            if "shift_start" not in kru:
                kru["shift_start"] = "05:00" if i < n // 2 else "15:00"
            if "shift_end" not in kru:
                kru["shift_end"]   = "13:00" if i < n // 2 else "23:00"
            if "max_hours" not in kru:
                kru["max_hours"]   = _DEFAULT_MAX_HOURS
            if "break_duration" not in kru:
                kru["break_duration"] = min_break

    assign_shifts(payload.get("drivers",    []), min_rest)
    assign_shifts(payload.get("conductors", []), min_rest)

    # === PREPROCESSING: hitung estimated_travel_time dari jarak + avg_speed ===
    for rute in payload.get("routes", []):
        if not rute.get("avg_speed"):
            rute["avg_speed"] = 15.0
        speed = float(rute["avg_speed"])
        dist  = float(rute.get("total_distance_km", 0))
        rute["estimated_travel_time_min"] = int((dist / speed) * 60) if speed > 0 else 60

    for rs in payload.get("route_stops", []):
        dist = _get_dist_halte(rs)
        speed = float(payload.get("routes", [{}])[0].get("avg_speed", 50) or 50)
        rs["estimated_travel_time_min"] = round((dist / speed) * 60) if speed > 0 else 5

    # =========================================================
    # [WAJIB-1] Filter bus: hanya yang status == "active"
    # Bus dengan status "maintenance" atau "retired" diexclude
    # =========================================================
    bus_aktif = [b for b in payload["buses"] if b.get("status", "active") == "active"]
    bus_excluded = [b for b in payload["buses"] if b.get("status", "active") != "active"]
    if bus_excluded:
        _progress(f"  [WAJIB-1] Exclude {len(bus_excluded)} bus non-active: "
                  f"{[b.get('plate_number', b['id']) for b in bus_excluded]}")

    if not bus_aktif:
        return {"status": "error", "errors": ["Tidak ada bus dengan status 'active' yang tersedia."]}

    # =========================================================
    # [WAJIB-2] Filter kru: exclude yang sedang cuti (daily_leaves)
    # Payload harus menyertakan field "daily_leaves": [employee_id, ...]
    # Laravel kirim list employee_id yang cuti hari ini
    # =========================================================
    daily_leaves = set(payload.get("daily_leaves", []))
    if daily_leaves:
        _progress(f"  [WAJIB-2] Exclude {len(daily_leaves)} kru yang cuti: {daily_leaves}")

    driver_aktif = [
        d for d in payload["drivers"]
        if d.get("employee_id") not in daily_leaves
        and d.get("is_available", 1) == 1
    ]
    cond_aktif = [
        c for c in payload["conductors"]
        if c.get("employee_id") not in daily_leaves
        and c.get("is_available", 1) == 1
    ]

    if not driver_aktif:
        return {"status": "error", "errors": ["Tidak ada driver yang tersedia (semua cuti atau tidak aktif)."]}
    if not cond_aktif:
        return {"status": "error", "errors": ["Tidak ada kondektur yang tersedia (semua cuti atau tidak aktif)."]}

    # === BUILD CTX ===
    total_bus     = len(bus_aktif)   # [WAJIB-1] total hanya bus aktif
    reserve_count = max(1, int(total_bus * _RESERVE_PCT))

    # [OPSIONAL-1] Build stop_coords map dari route_stops jika ada lat/lng
    stop_coords = {}
    for rs in payload.get("route_stops", []):
        sid = rs.get("stop_id")
        lat = rs.get("latitude") or rs.get("lat")
        lng = rs.get("longitude") or rs.get("lng") or rs.get("lon")
        if sid and lat is not None and lng is not None:
            stop_coords[sid] = {"lat": float(lat), "lng": float(lng)}

    ctx = {
        "rute_map":     {r["id"]: r for r in payload["routes"]},
        "bus_map":      {b["id"]: b for b in bus_aktif},        # [WAJIB-1] hanya bus aktif
        "driver_map":   {d["id"]: d for d in driver_aktif},    # [WAJIB-2] hanya kru aktif
        "cond_map":     {c["id"]: c for c in cond_aktif},      # [WAJIB-2] hanya kru aktif
        # [OPSIONAL-2] threshold_map sudah di sini sejak v4, dan P6 sekarang BENAR-BENAR memakainya
        "threshold_map":{t["bus_id"]: t for t in payload.get("maintenance_thresholds", [])},
        "prediksi_map": {},
        "min_rest":      min_rest,
        "total_bus":     total_bus,
        "reserve_count": reserve_count,
        "stop_coords":   stop_coords,   # [OPSIONAL-1]
    }

    print("\n[AI] Menghubungkan ke Model PKL...")
    for rute in payload["routes"]:
        rid = rute["id"]
        rname = rute.get("name", rid)
        for h in range(5, 24):
            try:
                # Memanggil jembatan AI (predict_demand)
                # TODO (Backend): Ganti parameter day_of_week, month, is_holiday dengan data asli dari schedule_date
                pred_val = predict_demand(rname, hour=h, day_of_week=0, month=6, is_holiday=0)
                ctx["prediksi_map"][(rid, h)] = pred_val
            except Exception as e:
                # Fallback: Mengambil rata-rata historis rute dari payload (dikirim backend), atau default 50
                fallback_val = rute.get("historical_avg_demand", 50)
                ctx["prediksi_map"][(rid, h)] = fallback_val
    print("[AI] Prediksi seluruh jam selesai!\n")

    route_stops_map = {}
    for rs in sorted(payload.get("route_stops", []), key=lambda x: x.get("sequence", 0)):
        rid = rs.get("route_id")
        if rid not in route_stops_map:
            route_stops_map[rid] = []
        route_stops_map[rid].append(rs)

    _progress("=" * 65)
    _progress(f"  v5.0 | Pop={pop_size} Gen={generations} CR={crossover_rate} MR={mutation_rate}")
    _progress(f"  Elitism={elitism_count} MinRest={min_rest}m TournK={_TOURNAMENT_K}")
    _progress(f"  Rute={len(payload['routes'])} BusAktif={total_bus} (reserve={reserve_count})")
    _progress(f"  DriverAktif={len(driver_aktif)} CondAktif={len(cond_aktif)}")
    _progress(f"  [W1] BusExclude={len(bus_excluded)} | [W2] KruCuti={len(daily_leaves)}")
    _progress(f"  [O1] StopCoords={len(stop_coords)} titik tersedia")
    _progress(f"  Ops: {_OPS_START}-{_OPS_END} | MaxJamKerja={_DEFAULT_MAX_HOURS}j | IstirahatSetelah={_REST_AFTER_HOURS}j")
    _progress(f"  MaintenanceWindow={_MAINTENANCE_WINDOW_MIN}mnt/bus/hari | BusMaxOps={_BUS_MAX_DAILY_MIN}mnt")
    _progress("=" * 65)

    start_time = time.time()

    populasi          = _buat_populasi_awal(payload, pop_size, bus_aktif, driver_aktif, cond_aktif)
    skor_terbaik      = -float("inf")
    jadwal_terbaik    = []
    log_per_gen       = []
    generasi_stagnasi = 0
    mutation_rate_aktif = mutation_rate
    baseline_penalty  = None

    for gen in range(generations):
        skor_pop = [(krom, _evaluasi_kromosom(krom, ctx)) for krom in populasi]
        skor_pop.sort(key=lambda x: x[1], reverse=True)

        if baseline_penalty is None:
            penalties_gen0   = sorted([-x[1] for x in skor_pop])
            baseline_penalty = max(penalties_gen0[len(penalties_gen0) // 2], 1)

        gen_fitness = skor_pop[0][1]
        if gen_fitness > skor_terbaik:
            skor_terbaik      = gen_fitness
            jadwal_terbaik    = [t.copy() for t in skor_pop[0][0]]
            generasi_stagnasi = 0
        else:
            generasi_stagnasi += 1

        # [OPSIONAL-3] Log per generasi dengan format siap INSERT ke schedule_optimizations
        if gen % 50 == 0 or gen == generations - 1:
            log_entry = {
                "generation":           gen,
                "penalty_score":        round(-skor_terbaik, 2),
                "fitness_score":        round(skor_terbaik, 4),
                "stagnasi":             generasi_stagnasi,
                "mutation_rate_aktif":  round(mutation_rate_aktif, 4),
                "timestamp_offset_sec": round(time.time() - start_time, 2),
                # Field tambahan untuk tabel schedule_optimizations:
                "population_size":      pop_size,
                "crossover_rate":       crossover_rate,
                "is_final":             (gen == generations - 1),
            }
            log_per_gen.append(log_entry)
            _progress(
                f"  Gen {gen:>4} | penalty={-skor_terbaik:>10.2f}"
                f" | stagnasi={generasi_stagnasi} | MR={mutation_rate_aktif:.3f}"
            )

        mutation_rate_aktif = min(0.50, mutation_rate * 4) if generasi_stagnasi > _STAGNASI_MUTASI else mutation_rate

        if generasi_stagnasi > _STAGNASI_RESTART:
            n_inject = int(pop_size * _DIVERSITY_INJECT_PCT)
            _progress(f"  *** Gen {gen}: inject {n_inject} kromosom (diversity)")
            elites_saved = [item[0] for item in skor_pop[:elitism_count]]
            inject_new   = _buat_populasi_awal(payload, n_inject, bus_aktif, driver_aktif, cond_aktif)
            populasi = elites_saved + [item[0] for item in skor_pop[elitism_count:pop_size - n_inject]] + inject_new
            generasi_stagnasi   = 0
            mutation_rate_aktif = mutation_rate
            continue

        elites        = [item[0] for item in skor_pop[:elitism_count]]
        generasi_baru = [[t.copy() for t in k] for k in elites]

        while len(generasi_baru) < pop_size:
            ia = _tournament(skor_pop)
            ib = _tournament(skor_pop)
            a, b = _crossover(ia, ib, crossover_rate, payload, min_rest,
                              bus_aktif, driver_aktif, cond_aktif)
            generasi_baru.append(_mutasi(a, payload, mutation_rate_aktif, min_rest,
                                         bus_aktif, driver_aktif, cond_aktif))
            if len(generasi_baru) < pop_size:
                generasi_baru.append(_mutasi(b, payload, mutation_rate_aktif, min_rest,
                                              bus_aktif, driver_aktif, cond_aktif))

        populasi = generasi_baru[:pop_size]

    waktu_eksekusi = time.time() - start_time
    _progress(f"\n  Selesai dalam {waktu_eksekusi:.2f} detik")
    _progress(f"  Penalty akhir: {-skor_terbaik:.2f} (baseline: {baseline_penalty:.2f})")
    _progress(f"  Reduksi penalty: {((baseline_penalty - (-skor_terbaik)) / baseline_penalty * 100):.1f}%")

    rute_map_out = ctx["rute_map"]
    bus_map_out  = ctx["bus_map"]
    prediksi_out = ctx["prediksi_map"]

    trips_output      = []
    total_penumpang   = 0
    total_bus_dipakai = set()
    rute_nama = {r["id"]: r.get("name", f"Rute {r['id']}") for r in payload["routes"]}

    # [WAJIB-4] Output siap masuk tabel trips + schedules
    for trip in sorted(jadwal_terbaik, key=lambda t: t.get("departure_min", 0)):
        dep_min  = trip.get("departure_min", 0)
        rid      = trip.get("route_id")
        bid      = trip.get("bus_id")
        rute     = rute_map_out.get(rid, {})
        bus      = bus_map_out.get(bid, {})
        arr_min  = dep_min + (rute.get("estimated_travel_time_min", 60) or 60)
        dep_hour = dep_min // 60
        kap      = bus.get("capacity") or _DEFAULT_BUS_CAPACITY
        demand   = prediksi_out.get((rid, dep_hour), 0)

        total_penumpang += min(demand, kap)
        total_bus_dipakai.add(bid)

        trips_output.append({
            # Field untuk tabel trips (Laravel tinggal INSERT langsung)
            "route_id":          rid,
            "route_name":        rute_nama.get(rid, f"Rute {rid}"),
            "bus_id":            bid,
            "driver_id":         trip.get("driver_id"),
            "conductor_id":      trip.get("conductor_id"),
            "departure_time":    _to_time_str(dep_min),
            "estimated_arrival": _to_time_str(arr_min),
            "passenger_load":    min(demand, kap),
            "capacity":          kap,
            # Stops untuk tabel stop_times (Laravel loop dan INSERT)
            "stops":             _hitung_stop_arrivals(dep_min, rid, route_stops_map),
        })

    total_penalti_abs = abs(skor_terbaik)
    fitness_norm = round(max(0.0, 1.0 - total_penalti_abs / max(baseline_penalty, 1)), 4)

    dep_times   = sorted(t.get("departure_min", 0) for t in jadwal_terbaik)
    headways    = [dep_times[i+1] - dep_times[i] for i in range(len(dep_times)-1)]
    avg_headway = round(sum(headways) / len(headways), 2) if headways else 0.0

    return {
        "status":                 "success",
        "execution_time_seconds": round(waktu_eksekusi, 2),
        "schedule_data": {
            # Field untuk tabel schedules
            "name":                  "Jadwal Optimal - Generated AI",
            "fitness_score":         fitness_norm,
            "total_penalty":         round(total_penalti_abs, 2),
            "baseline_penalty":      round(baseline_penalty, 2),
            "penalty_reduction_pct": round((baseline_penalty - total_penalti_abs) / max(baseline_penalty, 1) * 100, 2),
            "passenger_served":      total_penumpang,
            "avg_wait_min":          round(avg_headway / 2, 2),
            "is_optimal":            fitness_norm >= 0.80,
        },
        # [OPSIONAL-3] optimization_logs siap INSERT ke tabel schedule_optimizations
        # Field: generation, penalty_score, fitness_score, stagnasi,
        #        mutation_rate_aktif, timestamp_offset_sec, population_size,
        #        crossover_rate, is_final
        "optimization_logs": log_per_gen,
        "summary": {
            "buses_total":         len(payload["buses"]),
            "buses_active":        len(bus_aktif),           # [WAJIB-1]
            "buses_excluded":      len(bus_excluded),        # [WAJIB-1]
            "buses_allocated":     len(total_bus_dipakai),
            "buses_reserved":      total_bus - len(total_bus_dipakai),
            "drivers_active":      len(driver_aktif),        # [WAJIB-2]
            "conductors_active":   len(cond_aktif),          # [WAJIB-2]
            "crew_on_leave":       len(daily_leaves),        # [WAJIB-2]
            "total_trips":         len(trips_output),
            "avg_headway_minutes": avg_headway,
        },
        "trips": trips_output,
        "constraints_applied": {
            "bus_capacity":           _DEFAULT_BUS_CAPACITY,
            "max_driver_hours":       _DEFAULT_MAX_HOURS,
            "rest_after_hours":       _REST_AFTER_HOURS,
            "rest_duration_min":      _REST_DURATION_MIN,
            "reserve_pct":            _RESERVE_PCT,
            "maintenance_window_min": _MAINTENANCE_WINDOW_MIN,
            "ops_hours":              f"{_OPS_START}-{_OPS_END}",
            "bus_transfer_speed_kmh": _BUS_TRANSFER_SPEED_KMH,  # [OPSIONAL-1]
        },
    }

# =====================================================================
# MAIN
# =====================================================================
if __name__ == "__main__":
    if sys.stdin.isatty():
        base_dir = os.path.dirname(__file__)
        json_path = os.path.join(base_dir, "payload.json")
        
        if os.path.exists(json_path):
            with open(json_path, "r") as f:
                payload = json.load(f)
        else:
            print(json.dumps({
                "status": "error", 
                "errors": [f"File dummy {json_path} tidak ditemukan. Buat dulu filenya!"]
            }, ensure_ascii=False))
            sys.exit(1)
            
    else:
        # JALUR UTAMA: Dibaca lewat Laravel (sys.stdin)
        try:
            raw = sys.stdin.read()
            if not raw.strip():
                print(json.dumps({
                    "status": "error",
                    "errors": ["Tidak ada payload yang diterima dari stdin."]
                }, ensure_ascii=False))
                sys.exit(1)
            payload = json.loads(raw)
        except json.JSONDecodeError as e:
            print(json.dumps({
                "status": "error",
                "errors": [f"Payload JSON tidak valid: {str(e)}"]
            }, ensure_ascii=False))
            sys.exit(1)

    # Menjalankan optimasi (berlaku untuk data Laravel maupun data dummy file teks)
    try:
        hasil = jalankan_optimasi(payload)
        print(json.dumps(hasil, indent=2, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({
            "status": "error",
            "errors": [f"Gagal memproses data optimasi: {str(e)}"]
        }, ensure_ascii=False))
        sys.exit(1)


#         try:
#         raw = sys.stdin.read()
#         if not raw.strip():
#             print(json.dumps({
#                 "status": "error",
#                 "errors": ["Tidak ada payload yang diterima dari stdin."]
#             }, ensure_ascii=False))
#             sys.exit(1)
#         payload = json.loads(raw)
#     except json.JSONDecodeError as e:
#         print(json.dumps({
#             "status": "error",
#             "errors": [f"Payload JSON tidak valid: {str(e)}"]
#         }, ensure_ascii=False))
#         sys.exit(1)

#     hasil = jalankan_optimasi(payload)
#     print(json.dumps(hasil, indent=2, ensure_ascii=False))