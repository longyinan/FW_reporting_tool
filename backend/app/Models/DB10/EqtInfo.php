<?php

namespace App\Models\DB10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EqtInfo extends Model
{
    protected $connection = 'db10';
    protected $table = 'eqt_info';

    public function getInfos($ank_id, $parts_no = []): Collection{
        $builder = $this->where([
            ['nxs_ank_book_seq', '=', $ank_id],
            ['del_flg', '=', '0'],
            ['valid_flg', '=', '1']
        ])->orderBy('sort','asc');

        if( !empty($parts_no) ){
            $builder->whereIn('nxs_enquete_no', $parts_no);
        }

        return $builder->get();
    }

    public function eqtXml()
    {
        return $this->hasOne('App\Models\DB10\EqtcSrcVerCtl', 'nxs_ank_book_seq', 'nxs_ank_book_seq')
        ->selectRaw('questionnaire_xml[1] as structure_xml,questionnaire_xml[2]  as logic_xml')
        ->where([
            ['nxs_enquete_no', '=', $this->nxs_enquete_no],
            ['ver_id', '=', $this->last_upd_ver_id]
        ]);
    }
}
