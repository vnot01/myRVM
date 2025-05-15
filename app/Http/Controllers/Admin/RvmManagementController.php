<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReverseVendingMachine;
use Illuminate\Http\Request;

class RvmManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        // Logika untuk mengambil RVMs nanti
        return Inertia::render('Admin/RVMs/Index', ['rvms' => [] /* data dummy dulu */]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ReverseVendingMachine $reverseVendingMachine)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReverseVendingMachine $reverseVendingMachine)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReverseVendingMachine $reverseVendingMachine)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReverseVendingMachine $reverseVendingMachine)
    {
        //
    }
}
