<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="API Red Devils",
 *     version="1.0.0",
 *     description="API para gerenciamento de peladas de futebol",
 *     @OA\Contact(
 *         email="admin@reddevils.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8080/api",
 *     description="Servidor de Desenvolvimento"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class SwaggerController extends Controller
{
    //
}
