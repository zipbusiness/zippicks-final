// phpcs:disable
(function($, backendData) {
  function displayNotice(content, type = 'info') {
    const $existingNotice = $('.pressidium-notice');

    if ($existingNotice.length > 0) {
      $existingNotice.remove();
    }

    const $notice = $(`
        <div class="notice pressidium-notice inline notice-${type} notice-alt">
          <p>${content}</p>
        </div>
      `);
    $('#titlediv').append($notice);
  }

  function displaySuccessNotice(content) {
    displayNotice(content, 'success');
  }

  function displayErrorNotice(content) {
    displayNotice(content, 'error');
  }

  function purgeCache() {
    const requestPayload = {
      post_id: backendData.postID,
      action: 'purge_cache_single_post',
      nonce: backendData.purgeCacheNonce,
    };

    $.post(ajaxurl, requestPayload)
      .done(function(response) {
        displaySuccessNotice(backendData.purgeCacheSuccess);
      })
      .fail(function() {
        displayErrorNotice(backendData.purgeCacheError);
      });
  }

  function handleClick(e) {
    e.preventDefault();

    if (!confirm(backendData.purgeCacheConfirm)) {
      // User cancelled the action, bail early
      return;
    }

    purgeCache();
  }

  $(function() {
    const $button = $('#purgeCacheSinglePostButton');
    $button.on('click', handleClick);
  });
})(jQuery, pressidiumPurgeCacheClassicEditor);
