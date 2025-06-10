<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
  public function __construct()
  {
    $this->middleware(['auth', 'admin']);
  }

  public function index()
  {
    $users = User::all();
    return view('users.index', compact('users'));
  }

  public function create()
  {
    return view('users.create');
  }

  public function store(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|string|email|max:255|unique:users',
      'password' => 'required|string|min:8',
      'api_url' => 'required|url',
    ]);

    User::create([
      'name' => $request->name,
      'email' => $request->email,
      'password' => Hash::make($request->password),
      'api_url' => $request->api_url,
      'is_admin' => false,
    ]);

    return redirect()->route('users.index')->with('success', 'User created successfully.');
  }
}
