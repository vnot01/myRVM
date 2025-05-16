<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia; // Import Inertia
use Illuminate\Support\Facades\Hash; // Untuk hashing password
use Illuminate\Validation\Rule; // Untuk aturan validasi unik saat update
use Illuminate\Validation\Rules; // Untuk aturan validasi password
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <-- Tambahkan Auth facade
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- Tambahkan AuthorizesRequests trait
use Illuminate\Support\Facades\Storage; // <-- Tambahkan Storage facade
use Illuminate\Support\Str; // Untuk Str::random()

class UserManagementController extends Controller
{
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchTerm = $request->query('search');
        $roleFilter = $request->query('role'); // Untuk filter role nanti
        $statusFilter = $request->query('status'); // Untuk filter status email_verified_at atau is_active

        $users = User::query()
            ->when($searchTerm, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter, function ($query, $role) {
                if ($role !== 'all') { // Anggap 'all' untuk menampilkan semua
                    $query->where('role', $role);
                }
            })
            ->when($statusFilter, function ($query, $status) {
                if ($status === 'verified') {
                    $query->whereNotNull('email_verified_at');
                } elseif ($status === 'unverified') {
                    $query->whereNull('email_verified_at');
                }
                // Tambahkan filter untuk 'is_active' nanti
            })
            ->orderBy('created_at', 'desc') // Tampilkan user terbaru dulu
            ->paginate(10) // Paginasi per 10 item
            ->withQueryString(); // Agar parameter search dan filter tetap ada di link paginasi

        // Variabel untuk filter di frontend (opsional, bisa didefinisikan di Vue juga)
        $availableRoles = ['Admin', 'Operator', 'User']; // Sesuaikan dengan role Anda
        $availableRolesProp = ['Operator', 'User'];
        $availableStatuses = [ // Untuk verifikasi email
            ['value' => 'all', 'label' => 'Semua Status Verifikasi'],
            ['value' => 'verified', 'label' => 'Terverifikasi'],
            ['value' => 'unverified', 'label' => 'Belum Terverifikasi'],
        ];
        info('Available Roles: :',$availableRoles);
        info('Statuses: :',$availableStatuses);
        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status']), // Kirim filter aktif ke Vue
            'availableRoles' => $availableRoles, // Kirim opsi role
            'availableRolesProp' => $availableRolesProp,
            'availableStatuses' => $availableStatuses, // Kirim opsi status
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Hanya Admin yang boleh membuat user baru
        // $this->authorize('create', User::class); // Asumsi Anda punya UserPolicy

        // --- GANTI DENGAN CEK ROLE MANUAL ---
        // if (!Auth::user() || Auth::user()->role !== 'Admin') {
        //     abort(403, 'Hanya Admin yang dapat membuat pengguna baru.');
        // }
        // // --- AKHIR CEK ROLE MANUAL ---

        // $availableRoles = ['Admin', 'Operator', 'User']; // Sesuaikan dengan role Anda
        // $availableRolesProp = ['Operator', 'User'];
        // info('Available Roles: :',$availableRoles);
        // // info('Statuses: :',$availableStatuses);
        // return Inertia::render('Admin/Users/Create', [
        //     'availableRolesProp' => $availableRolesProp,
        //     `availableRoles` => $availableRoles,
        //     // 'defaultPassword' => Str::random(12),
        // ]);

        $availableRolesProp = ['Operator', 'User'];
        $dataToPass = [
            'availableRoles' => ['Admin', 'Operator', 'User'],
            'availableRolesProp' => $availableRolesProp,
            // 'defaultPassword' => Str::random(12),
            // tambahkan prop lain jika ada
        ];

        // dd($dataToPass); // <-- TAMBAHKAN INI UNTUK DEBUGGING

        return Inertia::render('Admin/Users/Create', $dataToPass);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $this->authorize('create', User::class);
        // --- GANTI DENGAN CEK ROLE MANUAL ---
        if (!Auth::user() || Auth::user()->role !== 'Admin') {
            abort(403, 'Hanya Admin yang dapat menyimpan pengguna baru.');
        }
            
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|string|in:Operator,User', // Sesuaikan dengan role yang valid
            'phone_number' => 'nullable|string|max:20',
            'citizenship' => 'required|string|in:WNI,WNA',
            'identity_type' => ['required_with:identity_number', 'string', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('citizenship') === 'WNI' && !in_array($value, ['KTP', 'Pasport'])) {
                    $fail('Tipe identitas tidak valid untuk WNI.');
                }
                if ($request->input('citizenship') === 'WNA' && $value !== 'Pasport') {
                    $fail('Tipe identitas tidak valid untuk WNA.');
                }
            }],
            'identity_number' => ['nullable', 'string', 'max:50', 'unique:users,identity_number,' . ($this->user?->id ?? 'NULL') . ',id', // Abaikan user saat ini jika update
                function ($attribute, $value, $fail) use ($request) {
                    $identityType = $request->input('identity_type');
                    if ($identityType === 'KTP') {
                        if (!preg_match('/^\d{16}$/', $value)) {
                            $fail('Nomor KTP harus terdiri dari 16 digit angka.');
                        }
                    } elseif ($identityType === 'Pasport') {
                        if (!preg_match('/^[A-Z0-9]{1,10}$/', $value)) { // Contoh: 1-10 karakter alfanumerik uppercase
                            $fail('Nomor Pasport tidak valid (1-10 alphanumeric uppercase).');
                        }
                    }
                }
            ],
        ]);

        try {
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role' => $validatedData['role'],
                'phone_number' => $validatedData['phone_number'],
                'citizenship' => $validatedData['citizenship'],
                'identity_type' => $validatedData['identity_type'],
                'identity_number' => $validatedData['identity_number'],
                'email_verified_at' => now(), // Anggap langsung terverifikasi jika dibuat oleh Admin
            ]);

            Log::info('User created by admin.', ['user_id' => $user->id, 'admin_id' => auth()->id()]);

            return redirect()->route('admin.users.index')
                             ->with('success', 'Pengguna "' . $user->name . '" berhasil ditambahkan.');

        } catch (\Exception $e) {
            Log::error('Error creating user by admin: ' . $e->getMessage());
            return redirect()->back()
                             ->with('error', 'Gagal menambahkan pengguna. Terjadi kesalahan server.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

   /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user) // Route model binding
    {
        // Hanya Admin yang boleh mengedit user
        // $this->authorize('update', $user); // Asumsi UserPolicy akan dibuat/digunakan

        // Jika tidak pakai Policy, cek manual:
        if (!Auth::user() || Auth::user()->role !== 'Admin') {
            abort(403, 'Hanya Admin yang dapat mengedit pengguna.');
        }

        $availableRoles = ['Admin', 'Operator', 'User']; // Sesuaikan dengan role Anda
        $availableRolesProp = ['Operator', 'User'];
        if (Auth::user()->id === $user->id && $user->role === 'Admin') {
            // Jika Admin mengedit dirinya sendiri, dia tidak bisa menurunkan role-nya
            // Atau Anda bisa memutuskan untuk tidak menampilkan field role sama sekali
        }


        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->only('id', 'name', 'email', 'role', 'phone_number', 'citizenship', 'identity_type', 'identity_number', 'email_verified_at', 'avatar', 'is_active'), // Kirim data user yang relevan
            'availableRolesProp' => $availableRolesProp,
            `availableRoles` => $availableRoles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user) // Route model binding
    {
        // $this->authorize('update', $user);
        if (!Auth::user() || Auth::user()->role !== 'Admin') {
            abort(403, 'Hanya Admin yang dapat memperbarui pengguna.');
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|string|in:Operator,User', // Admin tidak bisa diubah dari sini
            'phone_number' => 'nullable|string|max:20',
            'citizenship' => 'nullable|string|in:WNI,WNA', // Pastikan validasi ada
            'identity_type' => ['nullable','string', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('citizenship') === 'WNI' && !in_array($value, ['KTP', 'Pasport'])) {
                    $fail('Tipe identitas tidak valid untuk WNI.');
                }
                if ($request->input('citizenship') === 'WNA' && $value !== 'Pasport' && !empty($value)) { // Cek jika WNA dan tipe bukan pasport (dan tidak kosong)
                    $fail('Tipe identitas tidak valid untuk WNA.');
                }
            }],
            'identity_number' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($user->id),
                function ($attribute, $value, $fail) use ($request) {
                    if (empty($value)) return; // Lewati jika kosong
                    $identityType = $request->input('identity_type');
                    if ($identityType === 'KTP') {
                        if (!preg_match('/^\d{16}$/', $value)) {
                            $fail('Nomor KTP harus terdiri dari 16 digit angka.');
                        }
                    } elseif ($identityType === 'Pasport') {
                        if (!preg_match('/^[A-Z0-9]{1,10}$/', $value)) {
                            $fail('Nomor Pasport tidak valid (1-10 alphanumeric uppercase).');
                        }
                    }
                }
            ],
            'is_active' => 'sometimes|boolean', // Untuk status aktif/nonaktif
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validasi untuk avatar
        ]);

        try {
            $updateData = $validatedData;

            if ($request->hasFile('avatar')) {
                // Hapus avatar lama jika ada dan bukan default
                if ($user->avatar && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar))) {
                     Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
                }
                // Simpan avatar baru
                $path = $request->file('avatar')->store('avatars', 'public');
                $updateData['avatar'] = Storage::url($path); // Simpan URL publik
            }


            // Hanya update password jika diisi
            if ($request->filled('password')) {
                $request->validate([
                    'password' => ['required', 'confirmed', Rules\Password::defaults()],
                ]);
                $updateData['password'] = Hash::make($request->password);
            } else {
                // Jangan sertakan password dalam update jika tidak diubah
                unset($updateData['password']);
            }

            // Jika admin mencoba mengubah role dirinya sendiri (jika diizinkan)
            if (Auth::user()->id === $user->id && Auth::user()->role === 'Admin' && $request->input('role') !== 'Admin') {
                // Logika untuk mencegah admin menurunkan role dirinya sendiri, atau memerlukan konfirmasi khusus
                // Untuk sekarang, kita asumsikan ini tidak diizinkan melalui form ini jika role tidak ada di availableRoles
            }


            $user->update($updateData);
            Log::info('User updated by admin.', ['user_id' => $user->id, 'admin_id' => auth()->id()]);

            return redirect()->route('admin.users.index')
                             ->with('success', 'Data pengguna "' . $user->name . '" berhasil diperbarui.');

        } catch (\Exception $e) {
            Log::error('Error updating user by admin: ' . $e->getMessage(), ['user_id' => $user->id]);
            return redirect()->back()
                             ->with('error', 'Gagal memperbarui data pengguna. Terjadi kesalahan server.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
