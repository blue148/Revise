<?php
/**
 * @var modX $modx
 * @var modResource $resource
 * @var string $mode
 */
$corePath = $modx->getOption('revise.core_path', null, $modx->getOption('core_path', null, MODX_CORE_PATH));
switch ($modx->event->name) {
    case "OnBeforeDocFormSave":
        /* Create a ReviseResourceHistory record when updating a Resource */
        if (empty($reloadOnly) && !empty($resource) && isset($mode) && $mode === modSystemEvent::MODE_UPD) {
            $revise = $modx->getService('revise', 'Revise', $corePath . 'components/revise/model/revise/', array('core_path' => $corePath));

            /* get the resource fresh without the pending changes */
            /** @var modResource $existingResource */
            $existingResource = $modx->getObject('modResource', $id, false);

            /** @var modProcessorResponse $response */
            $response = $modx->runProcessor(
                'revise/resource/history/create',
                array(
                    'source' => $existingResource->get('id'),
                    'data' => $existingResource->toArray('', true, true, false)
                ),
                array('processors_path' => $revise->getOption('processorsPath'))
            );
            if ($response->isError()) {
                $modx->log(modX::LOG_LEVEL_ERROR, $response->getMessage(), '', 'modPlugin::Revise', __FILE__, __LINE__);
            }
        }
        break;
    case "OnDocFormRender":
        /* Render ReviseResourceDraft save/preview controls */
        $revise = $modx->getService('revise', 'Revise', $corePath . 'components/revise/model/revise/', array('core_path' => $corePath));
        
        /* Init Params for use in EXT below */
        $reviseParams = array(
            'connectorUrl' => $revise->getOption('connectorUrl'),
            'source' => $id,
            'draft' => 0
        );

        $data = '<script> var reviseParams = ' . $modx->toJSON($reviseParams) . ';</script>';

        /* add controls to the form */
        $data .= <<<HTML
<script> 
Ext.onReady(function () {
    Ext.defer(function () {
        var panel = Ext.getCmp('modx-resource-main-right');
        var buttons = Ext.getCmp('modx-action-buttons');
        var createDraft = function (reviseParams, preview) {
            /* Save content to "ta" for RTE (eg. codemirror,tinyMCE) */
            if (typeof MODx.editor != 'undefined') {
                MODx.editor.save();
            }

            var data = Ext.getCmp('modx-panel-resource').getForm().getValues();

            /* Update content from ta */
            data.content = data.ta;
            
            MODx.Ajax.request({
                url: reviseParams.connectorUrl,
                params: {
                    action: 'revise/resource/draft/create',
                    source: reviseParams.source,
                    data: Ext.encode(data)
                },
                listeners: {
                    success: {fn: function (result) {
                        if (preview == false) {
                            MODx.msg.status({
                                title: _('save_successful'),
                                message: result['success'] ? (result['message'] ? result['message'] : _('success')) : _('error'),
                                delay: 3
                            });
                        } else {
                            if (result['success']) {
                                var url = reviseParams.connectorUrl + '?action=revise/resource/draft/view&id=' + result.object.id + '&HTTP_MODAUTH=' + MODx.siteId;
                                window.open(url);
                            } else {
                                MODx.msg.status({
                                    title: _('failure'),
                                    message: result['message'] ? result['message'] : _('error'),
                                    delay: 3
                                });
                            }
                        }
                    }, scope: this},
                    failure: {fn: function (response, opts) {
                        MODx.msg.status({
                            title: _('failure'),
                            message: response.message ? response.message : _('error'),
                            delay: 3
                        });
                    }, scope: this}
                }
            });
        };

        buttons.add(
            {
                xtype: 'tbspacer',
                width: 8
            },
            {
                id: 'savedraft',
                name: 'savedraft',
                xtype: 'button',
                cls: 'x-btn x-btn-text bmenu x-btn-noicon',
                text: 'Save Draft',
                disabled: false,
                handler: function () {
                    /* create the draft */
                    createDraft(reviseParams, false);
                }
            },
            {
                xtype: 'tbspacer',
                width: 5
            },
            {
                id: 'previewdraft',
                name: 'previewdraft',
                xtype: 'button',
                cls: 'x-btn x-btn-text bmenu x-btn-noicon',
                text: 'Preview Draft',
                handler: function () {
                    createDraft(reviseParams, true);
                }
            }
        );

        //Refresh layout
        buttons.doLayout();
        panel.doLayout();


    }, 1000);
});
</script>
HTML;
        $modx->controller->addHtml($data);
        break;
}

return true;
