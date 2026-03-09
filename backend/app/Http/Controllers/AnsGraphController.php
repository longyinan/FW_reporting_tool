<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowGraphRequest;
use App\Services\AnsGraphService;

class AnsGraphController extends Controller
{
    public function __construct(protected AnsGraphService $ansGraphService) {}

    public function index(int $id)
    {
        return $this->ansGraphService->index($id);
    }

    public function showGraph(ShowGraphRequest $request, int $ank_id)
    {
        return $this->ansGraphService->showGraph($ank_id, $request->validated('question'));
    }
}

