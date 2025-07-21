// phpcs:disable
(function($, translatedStrings) {
  function elementExists($elem) {
    return $elem.length > 0;
  }

  function displayNotice($elem) {
    if (elementExists($('.pressidium-notice'))) {
      return;
    }

    const $notice = $(`
        <div class="notice pressidium-notice inline notice-warning notice-alt">
          <p>${translatedStrings.manualPurgeNotice}</p>
        </div>
      `);
    $elem.append($notice);
  }

  function checkForSavePanel() {
    const $savePromptElement = $('.entities-saved-states__text-prompt');
    if (elementExists($savePromptElement)) {
      displayNotice($savePromptElement);
    }
  }

  function supportsMutationObserver() {
    return 'MutationObserver' in window;
  }

  $(function() {
    if (!supportsMutationObserver()) {
      return;
    }

    const observer = new MutationObserver(checkForSavePanel);
    observer.observe($('#site-editor')[0], {
      childList: true,
      subtree: true,
    });
  });
})(jQuery, pressidiumFSETranslatedStrings);
