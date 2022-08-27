<?php
namespace verbb\footnotes\controllers;

use verbb\footnotes\Footnotes;

use craft\web\Controller;

use yii\web\Response;

class BaseController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings(): Response
    {
        $settings = Footnotes::$plugin->getSettings();

        return $this->renderTemplate('footnotes/settings', [
            'settings' => $settings,
        ]);
    }

}