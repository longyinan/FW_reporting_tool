<?php

namespace App\Models\DB10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrjInfo extends Model
{
    protected $connection = 'db10';
    protected $table = 'prj_info';
    public function get(int $ank_id)
    {
        $clean_flag = sprintf("CASE when questionnaire_state = '9' and date_part('day', '%s'::timestamp - upd_ymd_hms::timestamp) > end_page_switch_date THEN 1 ELSE 0 END as clean_flag", date('Y-m-d H:i:s'));
        return $this->select( DB::raw("*, {$clean_flag}") )->where([
             ['nxs_ank_book_seq', '=', $ank_id],
            ['del_flg', '=', '0']
        ])->first();
    }

    public function eqtInfos()
    {
        return $this->hasMany('App\Models\DB10\EqtInfo', 'nxs_ank_book_seq', 'nxs_ank_book_seq')->where([
            ['del_flg', '=', '0'],
            ['valid_flg', '=', '1']
        ])->orderBy('sort');
    }
}
