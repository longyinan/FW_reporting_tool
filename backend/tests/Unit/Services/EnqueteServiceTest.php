<?php

namespace Tests\Unit\Services;

use App\Services\EnqueteService;
use Mockery;
use PHPUnit\Framework\TestCase;

class EnqueteServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_xml_data_returns_question_and_logic_lists_by_part_no(): void
    {
        $eqtInfoMock = Mockery::mock('overload:App\Models\DB10\EqtInfo');
        $eqtInfoMock->shouldReceive('getInfos')->once()->with(99, [1, 2])->andReturn(collect([
            (object) ['nxs_enquete_no' => 1, 'last_upd_ver_id' => 10],
            (object) ['nxs_enquete_no' => 2, 'last_upd_ver_id' => 20],
        ]));

        $cacheMock = Mockery::mock('alias:Illuminate\Support\Facades\Cache');
        $cacheMock->shouldReceive('rememberForever')->once()->withArgs(function ($key, $closure) {
            return $key === 'eqt_xml_data:1:10' && is_callable($closure);
        })->andReturn([
            'questionList' => [
                $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA', [
                    ['catNo' => 1, 'name' => '男性', 'otherlimit' => '', 'othertype' => '', 'othernumax' => '', 'otherFa' => []],
                    ['catNo' => 2, 'name' => '女性', 'otherlimit' => '', 'othertype' => '', 'othernumax' => '', 'otherFa' => []],
                ]),
            ],
            'logicList' => ['skip' => ['IF A'], 'show' => ['IF B']],
        ]);
        $cacheMock->shouldReceive('rememberForever')->once()->withArgs(function ($key, $closure) {
            return $key === 'eqt_xml_data:2:20' && is_callable($closure);
        })->andReturn([
            'questionList' => [
                $this->makeQuestion('sc2', 'SC2', 'あなたの年齢をお知らせください。', 'NU', []),
            ],
            'logicList' => ['skip' => ['IF C'], 'show' => ['IF D']],
        ]);

        $service = new EnqueteService();
        $result = $service->getXmlData(99, [1, 2]);

        $this->assertSame([
            1 => [
                $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA', [
                    ['catNo' => 1, 'name' => '男性', 'otherlimit' => '', 'othertype' => '', 'othernumax' => '', 'otherFa' => []],
                    ['catNo' => 2, 'name' => '女性', 'otherlimit' => '', 'othertype' => '', 'othernumax' => '', 'otherFa' => []],
                ]),
            ],
            2 => [
                $this->makeQuestion('sc2', 'SC2', 'あなたの年齢をお知らせください。', 'NU', []),
            ],
        ], $result['questionList']);
        $this->assertSame([
            1 => ['skip' => ['IF A'], 'show' => ['IF B']],
            2 => ['skip' => ['IF C'], 'show' => ['IF D']],
        ], $result['logicList']);
    }

    public function test_get_xml_data_returns_target_logic_when_logic_name_is_specified(): void
    {
        $eqtInfoMock = Mockery::mock('overload:App\Models\DB10\EqtInfo');
        $eqtInfoMock->shouldReceive('getInfos')->once()->with(77, [3])->andReturn(collect([
            (object) ['nxs_enquete_no' => 3, 'last_upd_ver_id' => 30],
        ]));

        $cacheMock = Mockery::mock('alias:Illuminate\Support\Facades\Cache');
        $cacheMock->shouldReceive('rememberForever')->once()->andReturn([
            'questionList' => [
                $this->makeQuestion('sc3', 'SC3', 'あなたの職業をお知らせください。', 'SA', []),
            ],
            'logicList' => ['skip' => ['IF X'], 'show' => ['IF Y']],
        ]);

        $service = new EnqueteService();
        $result = $service->getXmlData(77, [3], ['logic_name' => 'show']);

        $this->assertSame(['IF Y'], $result['logicList'][3]);
    }

    public function test_get_question_list_merges_all_parts_question_list(): void
    {
        $eqtInfoMock = Mockery::mock('overload:App\Models\DB10\EqtInfo');
        $eqtInfoMock->shouldReceive('getInfos')->once()->with(88, [1, 2])->andReturn(collect([
            (object) ['nxs_enquete_no' => 1, 'last_upd_ver_id' => 11],
            (object) ['nxs_enquete_no' => 2, 'last_upd_ver_id' => 22],
        ]));

        $cacheMock = Mockery::mock('alias:Illuminate\Support\Facades\Cache');
        $cacheMock->shouldReceive('rememberForever')->once()->andReturn([
            'questionList' => [
                $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA', []),
                $this->makeQuestion('sc2', 'SC2', 'あなたの年齢をお知らせください。', 'NU', []),
            ],
            'logicList' => [],
        ]);
        $cacheMock->shouldReceive('rememberForever')->once()->andReturn([
            'questionList' => [
                $this->makeQuestion('sc3', 'SC3', 'あなたの職業をお知らせください。', 'SA', []),
            ],
            'logicList' => [],
        ]);

        $service = new EnqueteService();
        $result = $service->getQuestionList(88, [1, 2]);

        $this->assertSame([
            $this->makeQuestion('sc1', 'SC1', 'あなたの性別をお選びください。', 'SA', []),
            $this->makeQuestion('sc2', 'SC2', 'あなたの年齢をお知らせください。', 'NU', []),
            $this->makeQuestion('sc3', 'SC3', 'あなたの職業をお知らせください。', 'SA', []),
        ], $result);
    }

    private function makeQuestion(string $qCol, string $qNo, string $name, string $type, array $categories): array
    {
        return [
            'qCol' => $qCol,
            'qNo' => $qNo,
            'name' => $name,
            'type' => $type,
            'categories' => $categories,
        ];
    }
}
