Ext.onReady(function() {
    Ext.QuickTips.init();
    var page = MODx.load({ xtype: 'versionx-page-resource'});
    page.show();
});

VersionX.page.Resource = function(config) {
    config = config || {};
    config.type = 'resource';
    VersionX.page.Resource.superclass.constructor.call(this,config);
};
Ext.extend(VersionX.page.Resource,VersionX.page.Base);
Ext.reg('versionx-page-resource',VersionX.page.Resource);
