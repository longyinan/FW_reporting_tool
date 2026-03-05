<?php

namespace App\Models\DB50;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class QtpQuotaTable extends Model
{
    protected $connection = 'db50';
    protected $table = "pyxis2_sys.qtp_quota_table";

    public function getList($ank_id): Collection
    {
        return $this->where([
            ['nxs_ank_book_seq', '=', $ank_id],
            ['del_flg', '=', '0']
        ])
        ->whereNotNull('priority_level')//priority_levelがnullとき、用いてないと想定（workareaに置くだけ）
        ->orderBy('quota_param')->get();
    }

    public function cellInfos()
    {
        return $this->hasMany('App\Models\DB50\QtpQuotaCellinfo', 'quota_table_id', 'quota_table_id')->where('del_flg', 0)->orderBy('priority_level');
    }
}
