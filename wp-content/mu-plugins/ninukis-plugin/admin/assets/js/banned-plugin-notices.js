// phpcs:disable
(function($, bannedPlugins, translatedStrings) {
  function displayBannedNotice($elem) {
    if ($elem.find('.notice').length > 0) {
      // A notice already exists
      return;
    }

    const $notice = $(`
      <div class="notice inline notice-warning notice-alt">
        <p>${translatedStrings.bannedOnPressidium}</p>
      </div>
    `);
    $elem.prepend($notice);
  }

  function checkForBannedPlugins() {
    bannedPlugins.forEach((slug) => {
      const $pluginCard = $(`.plugin-card.plugin-card-${slug}`);
      if ($pluginCard.length > 0) {
        displayBannedNotice($pluginCard);
      }
    });
  }

  function supportsMutationObserver() {
    return 'MutationObserver' in window;
  }

  $(function() {
    checkForBannedPlugins();

    if (supportsMutationObserver()) {
      const observer = new MutationObserver(checkForBannedPlugins);
      observer.observe($('#plugin-filter')[0], {
        childList: true,
      });
    }
  });
})(jQuery, pressidiumBannedPlugins, pressidiumBannedPluginsTranslatedStrings);
