<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ZipArchive;

class ScormController extends Controller
{
    //
    public function export(Request $r) {
        $data = $r->validate(['module_json' => 'required|array', 'title' => 'required|string']);
        $tmp = storage_path('app/scorm_' . uniqid());
        @mkdir($tmp, 0777, true);
        // Write imsmanifest.xml (minimal 1.2)
        $manifest = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <manifest identifier="wbt" version="1.0" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd">
        <organizations default="ORG1">
        <organization identifier="ORG1" structure="hierarchical">
        <title>{$data['title']}</title>
        <item identifier="ITEM1" identifierref="RES1"><title>{$data['title']}</title></item>
        </organization>
        </organizations>
        <resources>
        <resource identifier="RES1" type="webcontent" adlcp:scormtype="sco" href="index.html">
        <file href="index.html"/>
        <file href="module.json"/>
        </resource>
        </resources>
        </manifest>
        XML;
        file_put_contents("$tmp/imsmanifest.xml", $manifest);
        // Bundle a super-simple player index.html that fetches module.json and bootstraps the React build (or a static minimal JS)
        file_put_contents("$tmp/module.json", json_encode($data['module_json']));
        file_put_contents("$tmp/index.html", "<!doctype html><html><body><div id='root'>Loading…</div><script>fetch('module.json').then(r=>r.json()).then(m=>{document.getElementById('root').innerText='SCORM stub loaded: '+(m.meta?.title||'Module');});</script></body></html>");
        $zipPath = storage_path('app/'.uniqid('scorm_').'.zip');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE);
        foreach (scandir($tmp) as $f) {
        if ($f === '.' || $f === '..') continue;
        $zip->addFile("$tmp/$f", $f);
        }
        $zip->close();
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
