<?php

$s = array(
    'debug' => false,
    'versioncount.resource' => 10,
    'versioncount.template' => 10,
    'versioncount.templatevariables' => 10,
    'versioncount.chunk' => 10,
    'versioncount.snippet' => 10,
    'versioncount.plugin' => 10,
    
    'formtabs.resource' => true,
    'formtabs.template' => true,
    'formtabs.templatevariables' => true,
    'formtabs.chunk' => true,
    'formtabs.snippet' => true,
    'formtabs.plugin' => true,

    'enable.resources' => true,
    'enable.templates' => true,
    'enable.templatevariables' => true,
    'enable.chunks' => true,
    'enable.snippets' => true,
    'enable.plugins' => true,
);

$settings = array();

foreach ($s as $key => $value) {
    if (is_string($value) || is_int($value)) { $type = 'textfield'; }
    elseif (is_bool($value)) { $type = 'combo-boolean'; }
    else { $type = 'textfield'; }

    $parts = explode('.',$key);
    if (count($parts) == 1) { $area = 'Default'; }
    else { $area = $parts[0]; }
    
    $settings['versionx.'.$key] = $modx->newObject('modSystemSetting');
    $settings['versionx.'.$key]->set('key', 'versionx.'.$key);
    $settings['versionx.'.$key]->fromArray(array(
        'value' => $value,
        'xtype' => $type,
        'namespace' => 'versionx',
        'area' => $area
    ));
}

return $settings;


