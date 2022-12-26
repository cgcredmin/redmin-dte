<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ApiUser;

class AuthController extends Controller
{
  public function __construct()
  {
    $this->middleware('auth:api', ['except' => ['login', 'refresh', 'logout']]);
  }

  public function username()
  {
    return 'api_key';
  }

  // public function password()
  // {
  //   return 'api_secret';
  // }
  /**
   * Get a JWT via given credentials.
   *
   * @param  Request  $request
   * @return Response
   */
  public function login(Request $request)
  {
    $this->validate($request, [
      'api_key' => 'required|string',
      'api_secret' => 'required|string',
    ]);

    $credentials = [
      'api_key' => $request->api_key,
      'password' => $request->api_secret,
    ];

    if (!($token = Auth::attempt($credentials))) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    return $this->respondWithToken($token);
  }

  /**
   * Get the authenticated User.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function me()
  {
    return response()->json(auth()->user());
  }

  /**
   * Log the user out (Invalidate the token).
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function logout()
  {
    auth()->logout();

    return response()->json(['message' => 'Successfully logged out']);
  }

  /**
   * Refresh a token.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function refresh()
  {
    return $this->respondWithToken(auth()->refresh());
  }

  /**
   * Get the token array structure.
   *
   * @param  string $token
   *
   * @return \Illuminate\Http\JsonResponse
   */
  protected function respondWithToken($token)
  {
    return response()->json([
      'access_token' => $token,
      'token_type' => 'bearer',
      // 'user' => auth()->user(),
      'expires_in' =>
        auth()
          ->factory()
          ->getTTL() *
        60 *
        24,
    ]);
  }
}