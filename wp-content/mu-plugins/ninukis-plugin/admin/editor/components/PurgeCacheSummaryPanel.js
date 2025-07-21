import { PluginPostStatusInfo } from '@wordpress/edit-post';
import { useState } from '@wordpress/element';
import { dispatch, useSelect } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import ConfirmDialog from './ConfirmDialog';

/**
 * PurgeCacheSummaryPanel component.
 *
 * @return {JSX.Element}
 */
const PurgeCacheSummaryPanel = () => {
  const [isConfirmDialogOpen, setIsConfirmDialogOpen] = useState(false);
  const [isRequesting, setIsRequesting] = useState(false);

  const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);

  const requestCachePurge = async () => {
    setIsRequesting(true);

    try {
      const formData = new FormData();
      formData.append('post_id', postId);
      formData.append('action', 'purge_cache_single_post');
      formData.append('nonce', pressidiumEditorPlugins.purgeCacheNonce);

      const response = await fetch(ajaxurl, {
        method: 'POST',
        body: formData,
      });

      const { data } = await response.json();

      if (!response.ok) {
        throw new Error(data);
      }

      dispatch('core/notices')
        .createSuccessNotice(
          __('Post purged from cache.'),
          { id: 'pressidium-cache-purge-success' }
        );
    } catch (error) {
      console.error(error);

      dispatch('core/notices')
        .createErrorNotice(
          __('Failed to purge post from cache.'),
          { id: 'pressidium-cache-purge-failure' }
        );
    } finally {
      setIsRequesting(false)
    }
  }

  const showDialog = () => {
    if (isRequesting) {
      return;
    }

    setIsConfirmDialogOpen(true);
  };

  const cancelDialog = () => {
    setIsConfirmDialogOpen(false);
  };

  const confirmDialog = async () => {
    setIsConfirmDialogOpen(false);
    await requestCachePurge();
  };

  return (
    <PluginPostStatusInfo>
      <Button
        variant="secondary"
        onClick={showDialog}
        isBusy={isRequesting}
        style={{ display: 'flex', justifyContent: 'center', width: '100%' }}
      >
        {__('Purge from cache')}
      </Button>
      <ConfirmDialog
        title={__('Purge from cache?')}
        isOpen={isConfirmDialogOpen}
        onCancel={cancelDialog}
        onConfirm={confirmDialog}
        cancelButtonText={__('Cancel')}
        confirmButtonText={__('Purge')}
      >
        {__('Are you sure you want to purge this post from cache?')}
      </ConfirmDialog>
    </PluginPostStatusInfo>
  );
};

export default PurgeCacheSummaryPanel;
