import os
import joblib
import numpy as np
import pandas as pd

# Menentukan lokasi file .pkl secara manual (tanpa butuh config.py)
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OPTIMIZED_MODEL_PATH = os.path.join(BASE_DIR, "models", "rf_final_optimized_2324.pkl")

_CACHED_ARTIFACTS = None

def load_model_artifacts():
    """Membuka toples berisi model, encoder, fitur, dan status log."""
    global _CACHED_ARTIFACTS
    
    if _CACHED_ARTIFACTS is not None:
        return _CACHED_ARTIFACTS
        
    if not os.path.exists(OPTIMIZED_MODEL_PATH):
        print(f"Error: Model tidak ditemukan di {OPTIMIZED_MODEL_PATH}")
        print("Silakan jalankan file training terlebih dahulu.")
        return None
        
    _CACHED_ARTIFACTS = joblib.load(OPTIMIZED_MODEL_PATH)
    return _CACHED_ARTIFACTS

def predict_demand(route_name, hour, day_of_week, month, is_holiday):
    """
    Fungsi utama untuk menebak jumlah penumpang.
    Bisa di-import dan dipanggil oleh Genetic Algorithm (GA) atau Backend Web.
    """
    artifacts = load_model_artifacts()
    if artifacts is None: 
        return 0
    
    model = artifacts['model']
    route_encoder = artifacts['route_encoder']
    features = artifacts['features']
    is_log_transformed = artifacts.get('is_log_transformed', False)
    
    # Target Encoding (Ubah nama rute jadi bobot angka)
    # Jika rute tidak ada di memori AI, berikan bobot default 0
    encoded_route_val = route_encoder.get(route_name, 0)
    
    # Susun Input sesuai urutan
    input_data = {
        'route_encoded': [encoded_route_val],
        'hour': [hour],
        'day_of_week': [day_of_week],
        'month': [month],
        'is_holiday': [is_holiday]
    }
    input_df = pd.DataFrame(input_data)[features]
    
    # Eksekusi Prediksi
    predicted_val = model.predict(input_df)[0]
    
    # Penawar Log-Transform (Expm1)
    if is_log_transformed:
        predicted_val = np.expm1(predicted_val)
    
    # Pastikan tidak ada jumlah penumpang minus atau desimal (orang tidak bisa dipecah)
    return max(0, int(round(predicted_val)))

if __name__ == "__main__":
    print(" MENGUJI JEMBATAN AI UNTUK GA \n")
    
    # Skenario 1: Jam Sibuk (Peak Hour)
    demand_1 = predict_demand('M15+', hour=8, day_of_week=0, month=10, is_holiday=0)
    print(" Skenario 1: Jam Masuk Kerja (M15+, Senin 08:00, Okt)")
    print(f"   Prediksi Penumpang Menunggu: {demand_1} orang\n")

    # Skenario 2: Jam Normal
    demand_2 = predict_demand('BX23', hour=13, day_of_week=2, month=4, is_holiday=0)
    print(" Skenario 2: Siang Hari Normal (BX23, Rabu 13:00, Apr)")
    print(f"   Prediksi Penumpang Menunggu: {demand_2} orang\n")

    # Skenario 3: Jam Sepi (Off-Peak)
    demand_3 = predict_demand('BM5', hour=23, day_of_week=5, month=7, is_holiday=0)
    print(" Skenario 3: Tengah Malam Weekend (BM5, Sabtu 23:00, Jul)")
    print(f"   Prediksi Penumpang Menunggu: {demand_3} orang\n")