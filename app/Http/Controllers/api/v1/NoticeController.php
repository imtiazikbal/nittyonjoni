<?php

namespace App\Http\Controllers\api\v1;

use AWS\CRT\HTTP\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class NoticeController extends ResponseController
{
    public function index()
    {
        $notices = DB::table('notice')->orderBy('created_at', 'desc')->first();
        return $this->sendResponse($notices, 'Notices retrieved successfully.');
    }

    public function notice(Request $req)
    {
       $notice = DB::table('notice')->first();
       if($notice)
        {DB::table('notice')->where('id', $notice->id)->update([
            'title' => $req->title
        ]);
        return $this->sendResponse($notice, 'Notices updated successfully.');}
       else{
        DB::table('notice')->insert([
            'title' => $req->title
        ]);
        return $this->sendResponse($notice, 'Notices store successfully.');
       }

    }
}
