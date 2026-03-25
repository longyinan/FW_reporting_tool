<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowGraphRequest;
use App\Http\Requests\ShowFaGraphRequest;
use App\Http\Requests\ShowCrossRequest;
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
        return $this->ansGraphService->showGraph($ank_id, $request->validated());
    }

    public function showFaGraph(ShowFaGraphRequest $request, int $ank_id)
    {
        return $this->ansGraphService->showFaGraph($ank_id, $request->validated());
    }

    public function showCross(ShowCrossRequest $request, int $ank_id)
    {
        return $this->ansGraphService->showCross(
            $ank_id,
            $request->validated('sideQno'),
            $request->validated('headQno'),
            $request->validated('filter')
        );
    }
}
