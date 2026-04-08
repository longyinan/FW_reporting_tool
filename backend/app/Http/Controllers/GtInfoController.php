<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GtInfoController extends Controller
{
    public function index($id){

        return view('pages.gtInfo', ['id' => $id]);
    }

    public function ankConfirm($id){

        return view('pages.ankConfirm', ['id' => $id]);
    }
}
