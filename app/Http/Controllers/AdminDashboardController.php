<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminDashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //return view('dashboard');
        $tours = [
            [
                'image' => 'https://source.unsplash.com/400x300/?maldives',
                'title' => 'Rajskie Malediwy',
                'description' => '7 dni wypoczynku na tropikalnej wyspie',
                'price' => 4999,
            ],
            [
                'image' => 'https://source.unsplash.com/400x300/?paris',
                'title' => 'Weekend w Paryżu',
                'description' => 'Zabytki, kuchnia i romantyczne spacery',
                'price' => 2199,
            ],
            // … więcej ofert
        ];

        return view('home', compact('tours'), [
            'unreadMessagesCount' => auth()->check()
                ? auth()->user()->messages()->unread()->count()
                : 0,

            'messages' => auth()->check()
                ? auth()->user()->messages()->latest()->limit(5)->get()
                : [],
        ]);
    }

    public function userList()
    {
        $users = User::all();
        return view('superadmin.users.index', compact('users'));
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
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
