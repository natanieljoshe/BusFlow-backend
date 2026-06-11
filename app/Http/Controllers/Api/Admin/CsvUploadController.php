<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsvUpload;
use Illuminate\Http\Request;

class CsvUploadController extends Controller
{
    public function index() { return response()->json(['data' => CsvUpload::with('uploader')->get()]); }
    
    public function show($id) { return response()->json(['data' => CsvUpload::with('uploader')->findOrFail($id)]); }
    
    public function store(Request $request) {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240'
        ]);
        
        $path = $request->file('file')->store('csv_uploads');
        
        $upload = CsvUpload::create([
            'uploaded_by' => $request->user()->id,
            'filename' => $path,
            'status' => 'pending',
            'uploaded_at' => now()
        ]);
        
        return response()->json(['data' => $upload], 201);
    }
    
    public function destroy($id) { 
        CsvUpload::destroy($id); 
        return response()->json(['message' => 'Deleted']); 
    }
}
