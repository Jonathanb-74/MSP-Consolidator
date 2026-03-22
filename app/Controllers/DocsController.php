<?php

namespace App\Controllers;

use App\Core\Controller;

class DocsController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('docs/index', [
            'pageTitle'   => 'Documentation',
            'breadcrumbs' => ['Dashboard' => '/', 'Documentation' => null],
        ]);
    }
}
