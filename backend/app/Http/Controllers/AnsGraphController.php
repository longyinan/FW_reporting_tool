<?php

namespace App\Http\Controllers;

use App\Services\AnsGraphService;
class AnsGraphController extends Controller
{
    public function __construct(protected AnsGraphService $ansGraphService ){}

    public function index(int $id){
        return $this->ansGraphService->index($id);
    }
}
