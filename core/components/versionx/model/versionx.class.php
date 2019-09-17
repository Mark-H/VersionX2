<?php
/**
 * VersionX
 *
 * Copyright 2011 by Mark Hamstra <hello@markhamstra.com>
 *
 * This file is part of VersionX, a real estate property listings component
 * for MODX Revolution.
 *
 * VersionX is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * VersionX is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * VersionX; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package versionx
*/

class VersionX {
    public $modx;
    private $chunks;
    private $tvs = array();
    public $config = array();
    public $categoryCache = array();
    public $debug = false;
    public $charset;

    /**
     * @param \modX $modx
     * @param array $config
     */
    function __construct(modX $modx,array $config = array()) {
        $this->modx =& $modx;

        $basePath = $this->modx->getOption('versionx.core_path',$config,$this->modx->getOption('core_path').'components/versionx/');
        $assetsUrl = $this->modx->getOption('versionx.assets_url',$config,$this->modx->getOption('assets_url').'components/versionx/');
        $assetsPath = $this->modx->getOption('versionx.assets_path',$config,$this->modx->getOption('assets_path').'components/versionx/');
        $this->config = array_merge(array(
            'base_bath' => $basePath,
            'core_path' => $basePath,
            'model_path' => $basePath.'model/',
            'processors_path' => $basePath.'processors/',
            'elements_path' => $basePath.'elements/',
            'templates_path' => $basePath.'templates/',
            'assets_path' => $assetsPath,
            'js_url' => $assetsUrl.'js/',
            'css_url' => $assetsUrl.'css/',
            'assets_url' => $assetsUrl,
            'connector_url' => $assetsUrl.'connector.php',

            'has_users_permission' => $this->modx->hasPermission('view_user'),
        ),$config);

        require_once dirname(__DIR__) . '/docs/version.inc.php';
        if (defined('VERSIONX_FULLVERSION')) {
            $this->config['version'] = VERSIONX_FULLVERSION;
        }
        $modelPath = $this->config['model_path'];
        $this->modx->addPackage('versionx', $modelPath);
        $this->modx->lexicon->load('versionx:default');
        
        $this->debug = $this->modx->getOption('versionx.debug',null,false);
    }

    /**
    * Gets a Chunk and caches it; also falls back to file-based templates
    * for easier debugging.
    *
    * @access public
    * @param string $name The name of the Chunk
    * @param array $properties The properties for the Chunk
    * @return string The processed content of the Chunk
    * @author Shaun "splittingred" McCormick
    */
    public function getChunk($name,$properties = array()) {
        $chunk = null;
        if (!isset($this->chunks[$name])) {
            $chunk = $this->_getTplChunk($name);
            if (empty($chunk)) {
                $chunk = $this->modx->getObject('modChunk',array('name' => $name),true);
                if ($chunk == false) return false;
            }
            $this->chunks[$name] = $chunk->getContent();
        } else {
            $o = $this->chunks[$name];
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setContent($o);
        }
        $chunk->setCacheable(false);
        return $chunk->process($properties);
    }

    /**
    * Returns a modChunk object from a template file.
    *
    * @access private
    * @param string $name The name of the Chunk. Will parse to name.chunk.tpl
    * @param string $postFix The postfix to append to the name
    * @return modChunk/boolean Returns the modChunk object if found, otherwise false.
    * @author Shaun "splittingred" McCormick
    */
    private function _getTplChunk($name,$postFix = '.tpl') {
        $chunk = false;
        $f = $this->config['elements_path'].'chunks/'.strtolower($name).$postFix;
        if (file_exists($f)) {
            $o = file_get_contents($f);
            /* @var modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk');
            $chunk->set('name',$name);
            $chunk->setContent($o);
        }
        return $chunk;
    }

    public function newVersionFor($class, $contentId, $mode)
    {
        switch ($class) {
            case 'vxResource':
                return $this->newResourceVersion($contentId, $mode);

            case 'vxTemplate':
                return $this->newTemplateVersion($contentId, $mode);

            case 'vxChunk':
                return $this->newChunkVersion($contentId, $mode);

            case 'vxSnippet':
                return $this->newSnippetVersion($contentId, $mode);

            case 'vxPlugin':
                return $this->newPluginVersion($contentId, $mode);

            case 'vxTemplateVar':
                return $this->newTemplateVarVersion($contentId, $mode);
        }
        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Call to ' . __METHOD__ . ' with unrecognised class ' . $class);
        return false;
    }

    /**
     * Creates a new version of a Resource.
     *
     * @param int|modResource|modStaticResource $resource
     * @param string $mode
     * @return bool
     *
     */
    public function newResourceVersion($resource, $mode = 'upd') {
        if ($resource instanceof modResource) {
            // We're retrieving the resource again to clean up raw post data we don't want.
            $resource = $this->modx->getObject('modResource',$resource->get('id'));
        } else {
            $resource = $this->modx->getObject('modResource',(int)$resource);
        }

        $rArray = $resource->toArray();

        /* @var vxResource $version */
        $version = $this->modx->newObject('vxResource');

        $v = array(
            'content_id' => $rArray['id'],
            'user' => $this->modx->user->get('id'),
            'mode' => $mode,
            'title' => $rArray[$this->modx->getOption('resource_tree_node_name',null,'pagetitle')],
            'context_key' => $rArray['context_key'],
            'class' => $rArray['class_key'],
            'content' => $resource->get('content'),
        );

        $version->fromArray($v);

        unset ($rArray['id'],$rArray['content']);
        $version->set('fields',$rArray);

        $tvs = $resource->getTemplateVars();
        $tvArray = array();
        /* @var modTemplateVar $tv */
        foreach ($tvs as $tv) {
            $tvArray[] = $tv->get(array('id','value'));
        }
        $version->set('tvs',$tvArray);

        $keepCount = $this->modx->getOption('versionx.versioncount.resource', null, '10');

        $this->deleteOldVersions("vxResource", $version, $keepCount);

        if($this->checkLastVersion('vxResource', $version)) {
            return $version->save();
        }
        return true;
    }


    /**
     * Creates a new version of a Template.
     *
     * @param int|\modTemplate $template
     * @param string $mode
     * @return bool
     *
     */
    public function newTemplateVersion($template, $mode = 'upd') {
        if ($template instanceof modTemplate) {
            /* Fetch it again to prevent getting stuck with raw post data */
            $template = $this->modx->getObject('modTemplate', $template->get('id'));
        } else {
            $template = $this->modx->getObject('modTemplate', (int)$template);
        }
        $tArray = $template->toArray();

        /* @var vxTemplate $version */
        $version = $this->modx->newObject('vxTemplate');

        $v = array(
            'content_id' => $tArray['id'],
            'user' => $this->modx->user->get('id'),
            'mode' => $mode,
        );

        $version->fromArray(array_merge($v,$tArray));

        $keepCount = $this->modx->getOption('versionx.versioncount.template', null, '10');

        $this->deleteOldVersions("vxTemplate", $version, $keepCount);

        if($this->checkLastVersion('vxTemplate', $version)) {
            return $version->save();
        }
        return true;
    }


    /**
     * Creates a new version of a Template Variable.
     *
     * @param int|\modTemplateVar $tv
     * @param string $mode
     * @return bool
     *
     */
    public function newTemplateVarVersion($tv, $mode = 'upd') {
        if ($tv instanceof modTemplateVar) {
            /* Fetch it again to prevent getting stuck with raw post data */
            $tv = $this->modx->getObject('modTemplateVar', $tv->get('id'));
        } else {
            $tv = $this->modx->getObject('modTemplateVar', (int)$tv);
        }

        $tArray = $tv->toArray();

        /* @var modTemplateVar $version */
        $version = $this->modx->newObject('vxTemplateVar');

        $v = array(
            'content_id' => $tArray['id'],
            'user' => $this->modx->user->get('id'),
            'mode' => $mode,
        );

        $version->fromArray(array_merge($v,$tArray));

        $keepCount = $this->modx->getOption('versionx.versioncount.templatevariables', null, '10');

        $this->deleteOldVersions("vxTemplateVar", $version, $keepCount);

        if($this->checkLastVersion('vxTemplateVar', $version)) {
            return $version->save();
        }
        return true;
    }
    /**
     * Create a new version of a Chunk.
     *
     * @param int|\modChunk $chunk
     * @param string $mode
     * @return bool
     */
    public function newChunkVersion($chunk, $mode = 'upd') {
        if ($chunk instanceof modChunk) {
            /* Fetch it again to prevent getting stuck with raw post data */
            $chunk = $this->modx->getObject('modChunk', $chunk->get('id'));
        } else {
            $chunk = $this->modx->getObject('modChunk', (int)$chunk);
        }

        // prevents resource groups from failing in MODX versions prior to 2.2.14 (see github #8992 + fix)
        if (!($chunk instanceof modChunk)) {
            return false;
        }

        $cArray = $chunk->toArray();

        /* @var vxChunk $version */
        $version = $this->modx->newObject('vxChunk');
        
        $v = array(
            'content_id' => $cArray['id'],
            'user' => $this->modx->user->get('id'),
            'mode' => $mode,
        );

        $version->fromArray(array_merge($v,$cArray));

        $keepCount = $this->modx->getOption('versionx.versioncount.chunk', null, '10');

        $this->deleteOldVersions("vxChunk", $version, $keepCount);

        if($this->checkLastVersion('vxChunk', $version)) {
            return $version->save();
        }
        return true;
    }

    /**
     * Creates a new version of a Snippet.
     *
     * @param int|\modSnippet $snippet
     * @param string $mode
     * @return bool
     */
    public function newSnippetVersion($snippet, $mode = 'upd') {
        if ($snippet instanceof modSnippet) {
            /* Fetch it again to prevent getting stuck with raw post data */
            $snippet = $this->modx->getObject('modSnippet', $snippet->get('id'));
        } else {
            $snippet = $this->modx->getObject('modSnippet', (int)$snippet);
        }

        $sArray = $snippet->toArray();

        /* @var vxSnippet $version */
        $version = $this->modx->newObject('vxSnippet');

        $v = array(
            'content_id' => $sArray['id'],
            'user' => $this->modx->user->get('id'),
            'mode' => $mode,
        );

        $version->fromArray(array_merge($v,$sArray));

        $keepCount = $this->modx->getOption('versionx.versioncount.snippet', null, '10');

        $this->deleteOldVersions("vxSnippet", $version, $keepCount);

        if($this->checkLastVersion('vxSnippet', $version)) {
            return $version->save();
        }
        return true;
    }

    /**
     * Creates a new version of a Plugin.
     *
     * @param int|\modPlugin $plugin
     * @param string $mode
     * @return bool
     */
    public function newPluginVersion($plugin, $mode = 'upd') {
        if ($plugin instanceof modPlugin) {
            /* Fetch it again to prevent getting stuck with raw post data */
            $plugin = $this->modx->getObject('modPlugin', $plugin->get('id'));
        } else {
            $plugin = $this->modx->getObject('modPlugin', (int)$plugin);
        }
        $pArray = $plugin->toArray();

        /* @var vxPlugin $version */
        $version = $this->modx->newObject('vxPlugin');

        $v = array(
            'content_id' => $pArray['id'],
            'user' => $this->modx->user->get('id'),
            'mode' => $mode,
        );

        $version->fromArray(array_merge($v,$pArray));

        $keepCount = $this->modx->getOption('versionx.versioncount.plugin', null, '10');

        $this->deleteOldVersions("vxPlugin", $version, $keepCount);

        if($this->checkLastVersion('vxPlugin', $version)) {
            return $version->save();
        }
        return true;
    }

    /**
     * Gets & prepares version details for output.
     *
     * @param string $class
     * @param int $id
     * @param bool $json
     * @param string $prefix
     * @return bool|array
     */
    public function getVersionDetails($class = 'vxResource',$id = 0, $json = false, $prefix = '') {
        $v = $this->modx->getObject($class, ['version_id' => $id]);
        /* @var xPDOObject $v */
        if ($v instanceof $class) {
            $vArray = $v->toArray();
            $vArray['mode'] = $this->modx->lexicon('versionx.mode.'.$vArray['mode']);

            /* Class specific processing */
            switch ($class) {
                case 'vxResource':
                    $vArray = array_merge($vArray,$vArray['fields']);

                    if ($vArray['parent'] != 0) {
                        /* @var modResource $parent */
                        $parent = $this->modx->getObject('modResource',$vArray['parent']);
                        if ($parent instanceof modResource) $vArray['parent'] = $parent->get('pagetitle') .' ('.$vArray['parent'].')';
                    }

                    /* Process content type */
                    /* @var modContentType $ct */
                    $ct = $this->modx->getObject('modContentType',$vArray['content_type']);
                    if ($ct instanceof modContentType)
                        $vArray['content_type'] = $ct->get('name');

                    $vArray['content'] = $this->_prepareCodeView($vArray['content']);

                    if ($vArray['content_dispo'] == 1) $vArray['content_dispo'] = $this->modx->lexicon('attachment');
                    else $vArray['content_dispo'] = $this->modx->lexicon('inline');

                    /* Process boolean values */
                    $vArray['published'] = (intval($vArray['published'])) ? $this->modx->lexicon('yes') : $this->modx->lexicon('no');
                    $vArray['hidemenu'] = (intval($vArray['hidemenu'])) ? $this->modx->lexicon('yes') : $this->modx->lexicon('no');
                    $vArray['isfolder'] = (intval($vArray['isfolder'])) ? $this->modx->lexicon('yes') : $this->modx->lexicon('no');
                    $vArray['richtext'] = (intval($vArray['richtext'])) ? $this->modx->lexicon('yes') : $this->modx->lexicon('no');
                    $vArray['searchable'] = (intval($vArray['searchable'])) ? $this->modx->lexicon('yes') : $this->modx->lexicon('no');
                    $vArray['cacheable'] = (intval($vArray['cacheable'])) ? $this->modx->lexicon('yes') : $this->modx->lexicon('no');
                    $vArray['deleted'] = (intval($vArray['deleted'])) ? $this->modx->lexicon('yes') : $this->modx->lexicon('no');

                    /* Process time stamps */
                    $df = $this->modx->config['manager_date_format'].' '.$this->modx->config['manager_time_format'];
                    $vArray['saved'] = ($vArray['saved'] != 0) ? date($df,strtotime($vArray['saved'])) : '';
                    $vArray['publishedon'] = ($vArray['publishedon'] != 0) ? date($df,strtotime($vArray['publishedon'])) : '';
                    $vArray['pub_date'] = ($vArray['pub_date'] != 0) ? date($df,strtotime($vArray['pub_date'])) : '';
                    $vArray['unpub_date'] = ($vArray['unpub_date'] != 0) ? date($df,strtotime($vArray['unpub_date'])) : '';

                    /* Get TV captions */
                    $tvArray = array();
                    foreach ($vArray['tvs'] as $tv) {
                        if (!isset($this->tvs[$tv['id']]) || empty($this->tvs[$tv['id']])) {
                            /* @var modTemplateVar $tvObj */
                            $tvObj = $this->modx->getObject('modTemplateVar',$tv['id']);
                            if ($tvObj instanceof modTemplateVar) {
                                $caption = $tvObj->get('caption');
                                if (empty($caption)) $caption = $tvObj->get('name');
                                $this->tvs[$tv['id']] = $caption;
                            } else {
                                $this->tvs[$tv['id']] = 'tv'.$tv['id'];
                            }
                        }
                        $tvArray[] = array_merge($tv,array('caption' => $this->tvs[$tv['id']]));
                    }
                    $vArray['tvs'] = $tvArray;
                    break;
                case 'vxTemplateVar':
                    $vArray['category'] = $this->getCategory($vArray['category']);
                    if (is_array($vArray['input_properties'])) {
                        foreach ($vArray['input_properties'] as $key => $value) {
                            if ($decoded = $this->modx->fromJSON($value)) {
                                $vArray['input_properties'][$key] = $decoded;
                            }
                        }
                    }
                    if (is_array($vArray['output_properties'])) {
                        foreach ($vArray['output_properties'] as $key => $value) {
                            if ($decoded = $this->modx->fromJSON($value)) {
                                $vArray['output_properties'][$key] = $decoded;
                            }
                        }
                    }
                    break;

                case 'vxTemplate':
                    $vArray['content'] =  $this->_prepareCodeView($vArray['content']);
                    $vArray['category'] = $this->getCategory($vArray['category']);
                    break;

                case 'vxChunk':
                    $vArray['snippet'] =  $this->_prepareCodeView($vArray['snippet']);
                    $vArray['category'] = $this->getCategory($vArray['category']);
                    break;

                case 'vxSnippet':
                    $vArray['snippet'] =  $this->_prepareCodeView($vArray['snippet']);
                    $vArray['category'] = $this->getCategory($vArray['category']);
                    break;

                case 'vxPlugin':
                    $vArray['plugincode'] =  $this->_prepareCodeView($vArray['plugincode']);
                    $vArray['category'] = $this->getCategory($vArray['category']);
                    break;
            }

            /* @var modUserProfile $up */
            $up = $this->modx->getObject('modUserProfile',array('internalKey' => $vArray['user']));
            if ($up instanceof modUserProfile) $vArray['user'] = $up->get('fullname');

            if (!empty($prefix)) {
                $ta = array();
                foreach ($vArray as $tk => $tv) {
                    $ta[$prefix.$tk] = $tv;
                }
                $vArray = $ta;
            }
            if ($json) return $this->modx->toJSON($vArray);
            return $vArray;
        }
        return false;
    }

    /**
     * @param $string
     *
     * @return string
     */
    private function _prepareCodeView($string) {
        $lines = explode("\n",$string);
        foreach ($lines as $idx => $line) {
            $pos = 0;
            while( substr($line, $pos, 1) == ' ') {
                $pos++;
            }
            $lines[$idx] = str_repeat('&nbsp;', $pos) . $this->htmlent(substr($line, $pos));
        }

        $lines = implode("<br />\n", $lines);
        return $lines;
    }

    /**
     * Checks the last saved version (if any).
     * Returns true if there is no earlier version, or something is different.
     * So if this returns true: go ahead and save the version.
     * If this returns false: nothing changed, don't bother.
     *
     * @param string $class
     * @param \xPDOObject $version
     *
     * @return bool
     */
    protected function checkLastVersion($class = 'vxResource', xPDOObject $version) {
        /* Get last version to make sure we've got some changes to save */
        $c = $this->modx->newQuery($class);
        $c->where(array('content_id' => $version->get('content_id')));
        $c->sortby('version_id','DESC');
        $c->limit(1);

        $lastVersion = $this->modx->getCollection($class,$c);
        $lastVersion = !empty($lastVersion) ? array_shift($lastVersion) : array();
        /* @var vxResource $lastVersion */

        /* If there's no earlier version, we can go ahead and
         return true to indicate we need to save the version */
        if (!($lastVersion instanceof $class)) { 
            if ($this->debug) $this->modx->log(xPDO::LOG_LEVEL_ERROR,"[VersionX] Saving a {$class} for ID {$version->get('content_id')}: No earlier version found.");
            return true;
        }

        $newVersionArray = $version->toArray();
        $lastVersionArray = $lastVersion->toArray();

        /* Get rid of excluded vars for the specific object. */
        $exclude = call_user_func(array($class,'getExcludeFields'));
        if ($this->debug) $this->modx->log(modX::LOG_LEVEL_ERROR,'[VersionX checkLastVersion] Exclude fields: ' . print_r($exclude, true));
        foreach ($exclude as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subfield) {
                    if (isset($newVersionArray[$key]) && isset($newVersionArray[$key][$subfield])) {
                        unset ($newVersionArray[$key][$subfield]);
                    }
                    if (isset($lastVersionArray[$key]) && isset($lastVersionArray[$key][$subfield])) {
                        unset ($lastVersionArray[$key][$subfield]);
                    }
                }
            } else {
                if (isset($lastVersionArray[$value])) { unset($lastVersionArray[$value]); }
                if (isset($newVersionArray[$value])) { unset($newVersionArray[$value]); }
            }
        }

        $newVersionFlat = $this->flattenArray($newVersionArray);
        $lastVersionFlat = $this->flattenArray($lastVersionArray);

        if ($this->debug) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[VersionX checkLastVersion] New: ' . print_r($newVersionArray, true));
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[VersionX checkLastVersion] New Flattened: ' . $newVersionFlat);
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[VersionX checkLastVersion] Last: ' . print_r($lastVersionArray, true));
            $this->modx->log(modX::LOG_LEVEL_ERROR,'[VersionX checkLastVersion] Last Flattened: ' . $newVersionFlat);
        }

        /* If the flattened arrays don't match there's a difference and we return true to indicate we need to save. */
        if ($newVersionFlat != $lastVersionFlat) {
            return true;
        }

        if ($this->debug) $this->modx->log(xPDO::LOG_LEVEL_ERROR,"[VersionX] Not saving a {$class} for ID {$version->get('content_id')}: No changes found.");
        /* If we got here, there was a last version but it seemed nothing changes.
        Return false to indicate to NOT save a new version. */
        return false;
    }

    /**
     * Flattens an array recursively.
     * @param array $array
     *
     * @return array|string
     */
    public function flattenArray(array $array = array()) {
        if (!is_array($array)) return (string)$array;

        $string = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = '{' . $this->flattenArray($value) .'}';
            }
            if (!empty($value)) {
                $string[] = $key . ':' . $value;
            }
        }
        $string = implode(',',$string);
        return $string;
    }

    /**
     * Outputs the JavaScript needed to add a tab to the panels.
     *
     * @param string $class
     */
    public function outputVersionsTab ($class = 'vxResource') {
        if (!class_exists($class)) {
            $path = $this->config['model_path'].'versionx/'.strtolower($class).'.class.php';
            if (file_exists($path)) {
                require_once ($path);
            } 
            if (!class_exists($class)) {
                $this->modx->log(modX::LOG_LEVEL_ERROR,'[VersionX::outputVersionsTab] Error loading class '.$class);
                return;
            }
        }
        $langs = $this->_getLangs();
        $jsurl = $this->config['js_url'].'mgr/';
        
        /* Load class & set inVersion to true, indicating we're not looking at the VersionX controller. */
        $this->modx->regClientStartupScript($jsurl.'versionx.class.js');
        $this->modx->regClientStartupHTMLBlock('
            <script type="text/javascript">
                VersionX.config = '.$this->modx->toJSON($this->config).';
                VersionX.inVersion = true;
                '.$langs.'
            </script>
        ');
        
        /* Get the different individual JS to add to the page */
        $tabjs = call_user_func(array($class,'getTabJavascript'));
        if (is_array($tabjs)) {
            foreach ($tabjs as $js) {
                $this->modx->regClientStartupScript($jsurl.$js);
            }
        }
        
        /* Get the template and register it */
        $tplName = call_user_func(array($class,'getTabTpl'));
        $tplFile = $this->config['templates_path'] . $tplName . '.tpl';
        if (file_exists($tplFile)) {
            $tpl = file_get_contents($tplFile);
            if (!empty($tpl)) {
                $this->modx->regClientStartupHTMLBlock($tpl);
            }
        }
        
        
    }

    /**
     * Gets language strings for use on non-VersionX controllers.
     * @return string
     */
    public function _getLangs() {
        $entries = $this->modx->lexicon->loadCache('versionx');
		$langs = 'Ext.applyIf(MODx.lang,' . $this->modx->toJSON($entries) . ');';
        return $langs;
    }

    /**
     * @param $id
     *
     * @return string
     */
    public function getCategory($id) {
        if (!$id || $id == 0) return '';
        if (isset($this->categoryCache[$id])) {
            return $this->categoryCache[$id];
        }
        /* @var modCategory $category */
        $category = $this->modx->getObject('modCategory',(int)$id);
        if ($category) {
            return $this->categoryCache[$id] = $category->get('category') . " ($id)";
        } else {
            return (string)$id;
        }
    }

    /**
     * Runs htmlentities() on the string with the proper character encoding.
     * @param string $string
     * @return string
     */
    public function htmlent($string = '') {
        if ($this->charset === null) {
            $this->charset = $this->modx->getOption('modx_charset', null, 'UTF-8');
        }
        return htmlentities($string, ENT_QUOTES | ENT_SUBSTITUTE, $this->charset);
    }

    /**
     * Checks if there are versions that need to be deleted
     * @param string $class
     * @param xPDOObject $version
     * @param int $keepCount
     */
    private function deleteOldVersions($class = "vxResource", xPDOObject $version, $keepCount){
        /* Get last version to make sure we've got some changes to save */
        $c = $this->modx->newQuery($class);
        $c->where(array('content_id' => $version->get('content_id')));
        $c->sortby('version_id','ASC');

        /** @var xPDOObject[] $versions */
        $versions = $this->modx->getCollection($class,$c);

        $versionCount = count($versions);
        $counter = 1;
        foreach ($versions as $version){
            if($counter > $versionCount - $keepCount) break;
            $version->remove();
            $counter++;
        }
    }

}

