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
use App\Models\PointAdjustment;
use Illuminate\Support\Facades\DB; 
use Barryvdh\Debugbar\Facade as Debugbar;


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
                $query->where(function ($q) use ($search) {
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
        info('Available Roles: :', $availableRoles);
        info('Statuses: :', $availableStatuses);
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
            'points' => 'nullable|integer|min:0', // Tambahkan ini
            'is_active' => 'nullable|boolean', // Dari form Vue, ini akan 'true' atau 'false' (boolean)',
            'identity_type' => [
                'required_with:identity_number',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('citizenship') === 'WNI' && !in_array($value, ['KTP', 'Pasport'])) {
                        $fail('Tipe identitas tidak valid untuk WNI.');
                    }
                    if ($request->input('citizenship') === 'WNA' && $value !== 'Pasport') {
                        $fail('Tipe identitas tidak valid untuk WNA.');
                    }
                }
            ],
            'identity_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:users,identity_number,' . ($this->user?->id ?? 'NULL') . ',id', // Abaikan user saat ini jika update
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
                'points' => $validatedData['points'],
                'is_active' => $validatedData['is_active'],
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
        // if (!Auth::user() || Auth::user()->role !== 'Admin') {
        //     abort(403, 'Hanya Admin yang dapat mengedit pengguna.');
        // }
        if (Auth::user()->role !== 'Admin') {
            abort(403, 'Hanya Admin yang dapat mengedit pengguna.');
        }
        $userData = $user->only(
            'id',
            'name',
            'email',
            'phone_number',
            'citizenship',
            'identity_type',
            'identity_number',
            'points',
            'role',
            'is_active',
            'email_verified_at', // Untuk info apakah sudah diverifikasi
            'avatar' // Untuk menampilkan avatar saat ini
        );
        $editableRoles = ['Admin', 'Operator', 'User'];
        if (Auth::id() === $user->id && $user->role === 'Admin') {
            $editableRoles = ['Admin'];
        }

        $dataToPass = [
            'user' => $userData,
            'availableRoles' => $editableRoles,
        ];

        info('data To Pass: :', $dataToPass);

        return Inertia::render('Admin/Users/Edit', $dataToPass);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user) // Route model binding
    {

        if (Auth::user()->role !== 'Admin') {
            abort(403, 'Hanya Admin yang dapat memperbarui pengguna.');
        }

        info('[UserUpdate] Request data received:', $request->all());
        if ($request->hasFile('avatar')) {
            info('[UserUpdate] Avatar file IS PRESENT in request.');
        }

        // 1. Definisikan Aturan Validasi Dasar
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            // Sesuaikan $editableRoles jika perlu dari method edit atau definisikan di sini
            'role' => ['required', 'string', Rule::in(Auth::id() === $user->id && $user->role === 'Admin' ? ['Admin'] : ['Admin', 'Operator', 'User'])],
            'phone_number' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone_number')->ignore($user->id)],
            'citizenship' => ['nullable', 'string', Rule::in(['WNI', 'WNA'])],
            'identity_type' => ['nullable', 'string', Rule::in(['KTP', 'Pasport'])], // Validasi lebih detail di bawah
            'identity_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'identity_number')->ignore($user->id)], // Validasi lebih detail di bawah
            'points' => 'required|integer|min:0', // Tambahkan ini
            'is_active' => 'required|boolean', // Dari form Vue, ini akan 'true' atau 'false' (boolean)
            'email_verified_manually' => 'sometimes|boolean',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];
        // 2. Tambahkan Aturan Validasi Kondisional
        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        // Validasi lebih detail untuk identity_type berdasarkan citizenship
        if ($request->filled('citizenship')) {
            $rules['identity_type'] = [
                'required_with:identity_number',
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    if (empty($value) && $request->filled('identity_number')) { // Jika ada no ID, tipe ID wajib
                        $fail('Tipe identitas wajib diisi jika nomor identitas diisi.');
                        return;
                    }
                    if (!empty($value)) { // Hanya validasi jika tipe identitas diisi
                        if ($request->input('citizenship') === 'WNI' && !in_array($value, ['KTP', 'Pasport'])) {
                            $fail('Tipe identitas tidak valid untuk WNI.');
                        }
                        if ($request->input('citizenship') === 'WNA' && $value !== 'Pasport') {
                            $fail('Tipe identitas hanya boleh Paspor untuk WNA.');
                        }
                    }
                }
            ];
        }

        // Validasi lebih detail untuk identity_number berdasarkan identity_type
        if ($request->filled('identity_type') && $request->filled('identity_number')) {
            $rules['identity_number'] = [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'identity_number')->ignore($user->id),
                function ($attribute, $value, $fail) use ($request) {
                    $identityType = $request->input('identity_type');
                    if ($identityType === 'KTP' && !preg_match('/^\d{16}$/', $value)) {
                        $fail('Nomor KTP harus 16 digit angka.');
                    } elseif ($identityType === 'Pasport' && !preg_match('/^[A-Z0-9]{1,12}$/', $value)) { // Anda pakai 1-10 sebelumnya, saya ubah ke 1-12
                        $fail('Nomor Paspor maks 12 karakter (huruf besar & angka).');
                    }
                }
            ];
        }

        // 3. Lakukan Validasi
        $validatedData = $request->validate($rules);
        info('[UserUpdate] Validation passed. Validated data:', $validatedData);
        DB::beginTransaction(); // Mulai transaksi
        try {
           // Langsung update properti model $user
            $user->name = $validatedData['name'];
            $user->email = $validatedData['email'];
            $user->phone_number = $validatedData['phone_number'];
            $user->role = $validatedData['role'];
            $user->citizenship = $validatedData['citizenship'];
            $user->identity_type = $validatedData['identity_type'];
            $user->identity_number = ($validatedData['identity_type'] && isset($validatedData['identity_number'])) ? $validatedData['identity_number'] : null;

            // --- PERBAIKAN LOGIKA BOOLEAN ---
            // is_active akan selalu ada di $validatedData karena 'required'
            $user->is_active = filter_var($validatedData['is_active'], FILTER_VALIDATE_BOOLEAN);
            Log::info('[UserUpdate] Setting is_active to:', ['is_active' => $user->is_active]);


            // // Penanganan email_verified_at
            $emailChanged = $user->isDirty('email'); // Cek apakah email field diubah SEBELUM assignment baru

            if ($emailChanged) {
                $user->email_verified_at = null; // Reset verifikasi jika email diubah
                Log::info('[UserUpdate] Email changed, email_verified_at reset to null.');
                // TODO: Kirim email verifikasi baru
            } elseif (array_key_exists('email_verified_manually', $validatedData)) {
                // Jika 'email_verified_manually' dikirim (artinya checkbox ada dan nilainya bisa true/false)
                if (filter_var($validatedData['email_verified_manually'], FILTER_VALIDATE_BOOLEAN) === true) {
                    $user->email_verified_at = now();
                    Log::info('[UserUpdate] Email manually verified, email_verified_at set to now.');
                } else {
                    // Jika checkbox tidak dicentang (false)
                    $user->email_verified_at = null;
                    Log::info('[UserUpdate] Email manual verification checkbox unchecked, email_verified_at set to null.');
                }
            }
            // Jika 'email_verified_manually' tidak ada di $validatedData (misalnya, checkbox tidak ada di form karena kondisi tertentu),
            // maka 'email_verified_at' tidak akan diubah oleh blok ini, kecuali jika emailnya sendiri yang berubah.

            // --- AKHIR PERBAIKAN LOGIKA BOOLEAN ---

            // Handle Avatar
            if ($request->hasFile('avatar')) {
                if ($user->avatar && Storage::disk('public')->exists(str_replace(Storage::url(''), '', $user->avatar))) {
                    Storage::disk('public')->delete(str_replace(Storage::url(''), '', $user->avatar));
                }
                $path = $request->file('avatar')->store('avatars', 'public');
                $updatePayload['avatar'] = Storage::url($path);
                info('[UserUpdate] New avatar processed.', ['url' => $updatePayload['avatar']]);
            }

            // Handle Password
            if (!empty($validatedData['password'])) {
                $updatePayload['password'] = Hash::make($validatedData['password']);
                info('[UserUpdate] Password will be updated.');
                // TODO: Notifikasi email perubahan password
            }

            // Handle Verifikasi Email Manual & Perubahan Email
            $emailChanged = $user->email !== $validatedData['email'];
            if ($emailChanged) {
                $updatePayload['email_verified_at'] = null; // Reset verifikasi jika email berubah
                info('[UserUpdate] Email changed, verification reset.');
                // TODO: Kirim email verifikasi baru
            } elseif (isset($validatedData['email_verified_manually'])) { // Cek apakah field ini dikirim
                if ($validatedData['email_verified_manually'] && !$user->email_verified_at) {
                    $updatePayload['email_verified_at'] = now();
                    info('[UserUpdate] Email manually verified.');
                } elseif (!$validatedData['email_verified_manually'] && $user->email_verified_at) {
                    $updatePayload['email_verified_at'] = null; // Admin un-verify
                    info('[UserUpdate] Email manually un-verified.');
                }
            }

            // Handle Poin
            // $previousPoints = $user->points;
            $previousPoints = $user->getOriginal('points');
            $newPoints = (int) $validatedData['points'];
            $user->points = $newPoints;
            if ($previousPoints !== $newPoints) {
                $updatePayload['points'] = $newPoints;
                PointAdjustment::create([
                    'user_id' => $user->id,
                    'adjusted_by_user_id' => Auth::id(),
                    'previous_points' => $previousPoints,
                    'points_changed' => $newPoints - $previousPoints,
                    'new_points' => $newPoints,
                    'reason' => $request->input('points_change_reason', 'Perubahan poin manual oleh ' . Auth::user()->name),
                ]);
                info('[UserUpdate] Points adjusted.', ['old' => $previousPoints, 'new' => $newPoints]);
            } else {
                // Jika poin tidak berubah, tidak perlu masukkannya ke $updatePayload agar tidak trigger event update jika tidak perlu
                // Namun, karena kita sudah ambil dari $validatedData, tidak masalah.
            }


            // 5. Lakukan Update
            // Cek apakah ada perubahan sebelum save untuk efisiensi (opsional)
            if ($user->isDirty()) { // Cek apakah ada field yang berubah
                info('[UserUpdate] Attempting to save user with points: if ($user->isDirty()) { ', ['points' => $user->points]);
                $user->save();
                info('User model saved to DB.', ['user_id' => $user->id, 'changed_fields' => $user->getChanges()]);
            } else {
                info('No changes detected for user model, save skipped.', ['user_id' => $user->id]);
            }
            info('User updated successfully in DB.', ['user_id' => $user->id, 'admin_id' => Auth::id()]);

            DB::commit(); // Commit transaksi

            return redirect()->route('admin.users.index')
                ->with('success', 'Data pengguna "' . $user->name . '" berhasil diperbarui.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack(); // Rollback jika validasi gagal (seharusnya sudah ditangani $request->validate())
            Log::warning('[UserUpdate] ValidationException after initial pass (should not happen often):', ['errors' => $e->errors()]);
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaksi jika ada error lain
            Log::error('[UserUpdate] Exception during user update: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => Str::limit($e->getTraceAsString(), 1000)
            ]);
            return redirect()->back()
                ->with('error', 'Gagal memperbarui data pengguna. Terjadi kesalahan server.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user) // Route model binding
    {
        // Otorisasi: Hanya Admin yang boleh menghapus
        // Nanti akan diganti dengan Policy: $this->authorize('delete', $user);
        if (Auth::user()->role !== 'Admin') {
            Log::warning('UserManagement: Unauthorized attempt to delete user.', ['attempted_by_user_id' => Auth::id(), 'target_user_id' => $user->id]);
            // Mengembalikan ke index dengan pesan error karena ini adalah request Inertia via router.delete
            return redirect()->route('admin.users.index')->with('error', 'Anda tidak diizinkan untuk menghapus pengguna.');
        }

        // Mencegah Admin menghapus akunnya sendiri
        if (Auth::id() === $user->id) {
            Log::warning('UserManagement: Admin attempted to delete self.', ['admin_user_id' => Auth::id()]);
            return redirect()->route('admin.users.index')->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // Mencegah Admin menghapus Admin lain (kecuali jika Anda adalah Super Admin, misal ID 1)
        // Ini bisa jadi lebih kompleks jika ada banyak Admin. Untuk sekarang, kita izinkan Admin menghapus Admin lain (selain dirinya).
        if ($user->role === 'Admin' && Auth::id() !== 1) { // Contoh jika hanya user ID 1 yang bisa hapus Admin lain
            Log::warning('UserManagement: Admin attempted to delete another Admin without super privileges.', ['admin_user_id' => Auth::id(), 'target_admin_id' => $user->id]);
            return redirect()->route('admin.users.index')->with('error', 'Anda tidak dapat menghapus akun Admin lain.');
        }


        // Mulai transaksi jika Anda melakukan beberapa operasi database (misalnya, mencatat ke log aktivitas)
        DB::beginTransaction();
        try {
            $userName = $user->name; // Simpan nama untuk pesan flash
            $actionMessage = '';

            // Logika Hapus Bersyarat
            // Cek apakah user memiliki deposit atau poin. Anggap ada relasi 'deposits' di model User.
            $hasActivity = $user->deposits()->exists() || $user->points > 0;

            if ($hasActivity) {
                // Nonaktifkan pengguna
                $user->is_active = false;
                $user->save();
                $actionMessage = 'dinonaktifkan';
                Log::info('User deactivated due to existing activity.', ['user_id' => $user->id, 'admin_id' => Auth::id()]);
                // TODO: Catat ke UserActivityLog jika ada
                // UserActivityLog::create([...]);
            } else {
                // Hapus pengguna secara permanen
                $user->delete();
                $actionMessage = 'dihapus secara permanen';
                Log::info('User permanently deleted.', ['user_id' => $user->id, 'admin_id' => Auth::id()]);
            }

            DB::commit();

            return redirect()->route('admin.users.index')
                             ->with('success', "Pengguna \"{$userName}\" berhasil {$actionMessage}.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error during user deletion/deactivation: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'admin_id' => Auth::id(),
                'trace' => Str::limit($e->getTraceAsString(), 1000)
            ]);
            return redirect()->route('admin.users.index')
                             ->with('error', 'Gagal memproses permintaan. Terjadi kesalahan server.');
        }
    }
}
