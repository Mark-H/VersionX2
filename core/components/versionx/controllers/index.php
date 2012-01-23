<?php
/**
 * VersionX
 *
 * Copyright 2011 by Mark Hamstra <hello@markhamstra.com>
 *
 * VersionX is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * VersionX is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * VersionX; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 */

require_once dirname(dirname(__FILE__)).'/model/versionx.class.php';
$versionx = new VersionX($modx);
$versionx->initialize('mgr');
$action = $modx->getObject('modAction',array(
    'namespace' => 'versionx',
    'controller' => 'controllers/index',
));
if ($action) $action = $action->get('id');
$modx->regClientStartupHTMLBlock('
<script type="text/javascript">
    Ext.onReady(function() {
        VersionX.config = '.$modx->toJSON($versionx->config).';
        VersionX.action = '.$action.';
    });
</script>');
$modx->regClientStartupScript($versionx->config['js_url'].'mgr/versionx.class.js');

switch ($_REQUEST['action']) {
    case 'resource':
        /* If an ID was passed, fetch that version into a record array. */
        if (intval($_REQUEST['vid']) > 0) {
            $v = $versionx->getVersionDetails('vxResource',intval($_REQUEST['vid']),true);
            if ($v !== false)
                $modx->regClientStartupHTMLBlock('
                    <script type="text/javascript">VersionX.record = '.$v.'; </script>
                    <style type="text/css">
                        .ext-gecko .x-form-text, .ext-ie8 .x-form-text {padding-top: 0;}
                        .vx-added .x-form-item-label { color: green; } .vx-changed .x-form-item-label { color: #dd6600; } .vx-removed .x-form-item-label { color: #ff0000; }
                    </style>
                ');
        }
        /* If an ID to compare to was passed, fetch that aswell. */
        if (intval($_REQUEST['cmid']) > 0) {
            $v = $versionx->getVersionDetails('vxResource',intval($_REQUEST['cmid']),true);
            if ($v !== false)
                $modx->regClientStartupHTMLBlock('<script type="text/javascript">VersionX.cmrecord = '.$v.'; </script>');
        }

        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/action.resource.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/common/panel.common.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/common/grid.common.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/common/panel.content.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/resources/detailpanel/panel.tvs.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/resources/detailpanel.v21.resources.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/resources/combo.versions.resources.js');        
    break;
    
    case 'template':
        /* If an ID was passed, fetch that version into a record array. */
        if (intval($_REQUEST['vid']) > 0) {
            $v = $versionx->getVersionDetails('vxTemplate',intval($_REQUEST['vid']));
            if ($v !== false)
                $v['content'] =  nl2br(str_replace(' ', '&nbsp;',htmlentities($v['content'])));
                $modx->regClientStartupHTMLBlock('
                    <script type="text/javascript">VersionX.record = '.$modx->toJSON($v).'; </script>
                    <style type="text/css">
                        .ext-gecko .x-form-text, .ext-ie8 .x-form-text {padding-top: 0;}
                        .vx-added .x-form-item-label { color: green; } .vx-changed .x-form-item-label { color: #dd6600; } .vx-removed .x-form-item-label { color: #ff0000; }
                    </style>
                ');
        }
        /* If an ID to compare to was passed, fetch that aswell. */
        if (intval($_REQUEST['cmid']) > 0) {
            $v = $versionx->getVersionDetails('vxTemplate',intval($_REQUEST['cmid']));
            if ($v !== false)
            {
                $v['content'] =  nl2br(str_replace(' ', '&nbsp;',htmlentities($v['content'])));
                $modx->regClientStartupHTMLBlock('<script type="text/javascript">VersionX.cmrecord = '.$modx->toJSON($v).'; </script>');
            }
        }

        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/action.template.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/common/panel.common.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/common/grid.common.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/common/panel.content.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/templates/detailpanel.templates.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/templates/combo.versions.templates.js'); 
    break;
    
    case 'index':
    default:
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/action.index.js');
        /* Resources */
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/resources/panel.resources.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/resources/grid.resources.js');
        /* Templates */
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/templates/panel.templates.js');
        $modx->regClientStartupScript($versionx->config['js_url'].'mgr/templates/grid.templates.js');

    break;
}


return '<div id="versionx"></div>';

?>
