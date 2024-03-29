<?php
/**
 * Created by PhpStorm.
 * User: Qurious Click
 * Date: 6/12/2017
 * Time: 2:34 PM
 */

namespace app\assets\admin\dashboard;

use yii\web\AssetBundle;

/**
 * @author Djava UI <support@djavaui.com>
 */
class DashboardTwoAsset extends AssetBundle {

    public $basePath = '@webroot';
    public $baseUrl = '@asset';
    public $css = [
        'admin/css/reset.css',
        'admin/css/layout.css',
        'admin/css/components.css',
        'admin/css/plugins.css',
        'admin/css/yii-custom.css',
        'admin/css/themes/default.theme.css',
        'admin/css/custom.css'
    ];
    public $js = [
        'admin/js/apps.js',
        'admin/js/pages/blankon.form.element.js',
        'admin/js/whats42nite.dashboard.js',
        'admin/js/demo.js',
    ];
    public $depends = [
        'app\assets\admin\CoreAsset',
        'app\assets\admin\form\PageLevelElementAsset',
    ];

}
