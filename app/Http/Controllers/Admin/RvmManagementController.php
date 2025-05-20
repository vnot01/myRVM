<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // Untuk Str::random()
use Illuminate\Validation\Rule; // Untuk Rule::in()
use App\Models\ReverseVendingMachine; // Pastikan model diimpor
use Inertia\Inertia; // Import Inertia
use Illuminate\Support\Facades\Log;


class RvmManagementController extends Controller
{
    // app/Http/Controllers/Admin/RvmManagementController.php
    public function index(Request $request) // Tambahkan Request $request
    {
        $query = ReverseVendingMachine::orderBy('name', 'asc');

        // Jika ada parameter 'search'
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('location_description', 'LIKE', "%{$searchTerm}%")
                ->orWhere('api_key', 'LIKE', "%{$searchTerm}%"); // Mungkin juga cari berdasarkan API Key
            });
        }

        $rvms = $query->paginate(10)
                    ->through(fn ($rvm) => [
                        'id' => $rvm->id,
                        'name' => $rvm->name,
                        'location_description' => $rvm->location_description,
                        'status' => $rvm->status,
                        'api_key' => $rvm->api_key,
                        'created_at_formatted' => $rvm->created_at->translatedFormat('d M Y, H:i'),
                    ])
                    ->withQueryString(); // Penting agar parameter search tetap ada di link paginasi
        Log::info('RVMs: Index Opened!');
        return Inertia::render('Admin/RVMs/Index', [
            'rvms' => $rvms,
            'filters' => $request->only(['search']), // Kirim filter kembali ke view
        ]);
    }

    // /**
    //  * Display a listing of the resource.
    //  */
    // public function index()
    // {
    //     //
    //     // Logika untuk mengambil RVMs
    //     $rvms = ReverseVendingMachine::orderBy('name', 'asc') // Urutkan berdasarkan nama
    //                                 ->paginate(10) // Ambil 10 per halaman
    //                                 ->through(fn ($rvm) => [ // Transformasi data yang dikirim ke frontend
    //                                     'id' => $rvm->id,
    //                                     'name' => $rvm->name,
    //                                     'location_description' => $rvm->location_description,
    //                                     'status' => $rvm->status,
    //                                     'api_key' => $rvm->api_key,
    //                                     // Format tanggal agar mudah dibaca di frontend jika perlu,
    //                                     // atau biarkan frontend yang format
    //                                     'created_at_formatted' => $rvm->created_at->translatedFormat('d M Y, H:i'),
    //                                 ]);

    //     return Inertia::render('Admin/RVMs/Index', [
    //         'rvms' => $rvms, // Data RVM yang sudah dipaginasi dan ditransformasi
    //         'filters' => request()->only(['search', 'status']), // Jika ada filter nanti
    //     ]);
    // }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Log::info('RVMs: Created!');
        //
        // Metode create, store, edit, update, destroy akan ditambahkan nanti
        return Inertia::render('Admin/RVMs/Create', [
        // Anda bisa mengirim data tambahan jika diperlukan,
        // misalnya daftar status RVM yang valid
        'available_statuses' => ['active', 'inactive', 'maintenance','full'],
    ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('RVMs: Stored!');
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:reverse_vending_machines,name',
            'location_description' => 'nullable|string|max:1000',
            'status' => ['required', Rule::in(['active', 'inactive', 'maintenance', 'full'])],
        ]);

        // Generate API Key unik
        // Loop untuk memastikan keunikan, meskipun kemungkinannya sangat kecil untuk collision
        do {
            $apiKey = 'RVM' . now()->format('ymd') . '-' . Str::random(32);
        } while (ReverseVendingMachine::where('api_key', $apiKey)->exists());

        $rvm = ReverseVendingMachine::create([
            'name' => $validatedData['name'],
            'location_description' => $validatedData['location_description'],
            'status' => $validatedData['status'],
            'api_key' => $apiKey,
            // 'latitude' => $request->input('latitude'), // Jika ada field ini
            // 'longitude' => $request->input('longitude'), // Jika ada field ini
        ]);

        // Log::info('RVMs Stored data: Stored!: '.$rvm);
        return redirect()->route('admin.rvms.index')->with('success', 'Mesin '.$rvm->name. ' ditambahkan. Status: ' . $rvm->status);
    }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(ReverseVendingMachine $reverseVendingMachine)
    // {
    //     //
    // }

   /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReverseVendingMachine $rvm) // Route Model Binding
    {
        Log::info('RVMs: Editing RVM ID: ' . $rvm->id);
        return Inertia::render('Admin/RVMs/Edit', [
            'rvm' => [ // Kirim hanya data yang diperlukan dan aman
                'id' => $rvm->id,
                'name' => $rvm->name,
                'location_description' => $rvm->location_description,
                'status' => $rvm->status,
                // Jangan kirim API Key ke form edit jika tidak untuk ditampilkan/diedit
            ],
            'available_statuses' => ['active', 'inactive', 'maintenance', 'full'], // 'full' juga dimasukkan
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReverseVendingMachine $rvm) // Route Model Binding
    {
        Log::info('RVMs: Updating RVM ID: ' . $rvm->id);
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('reverse_vending_machines')->ignore($rvm->id)],
            'location_description' => 'nullable|string|max:1000',
            'status' => ['required', Rule::in(['active', 'inactive', 'maintenance', 'full'])],
        ]);

        $rvm->update($validatedData);

        return redirect()->route('admin.rvms.index')->with('success', 'Data Mesin RVM berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReverseVendingMachine $rvm) // Route Model Binding
    {
        Log::info('RVMs: Deleting RVM ID: ' . $rvm->id . ' Name: ' . $rvm->name);
        try {
            $rvmName = $rvm->name; // Simpan nama sebelum dihapus untuk pesan flash
            $rvm->delete();
            return redirect()->route('admin.rvms.index')->with('success', "Mesin RVM '{$rvmName}' berhasil dihapus.");
        } catch (\Exception $e) {
            // Tangani jika ada error, misalnya karena foreign key constraint
            report($e); // Laporkan exception
            return redirect()->route('admin.rvms.index')->with('error', "Gagal menghapus RVM '{$rvm->name}'. Mungkin masih terkait dengan data lain.");
        }
    }
}
