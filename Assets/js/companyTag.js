Mautic.createCompanyTag = function (el) {
    var newFound = false;
    mQuery('#' + mQuery(el).attr('id') + ' :selected').each(function(i, selected) {
        if (!mQuery.isNumeric(mQuery(selected).val())) {
            newFound = true;
        }
    });

    if (!newFound) {
        return;
    }

    Mautic.activateLabelLoadingIndicator(mQuery(el).attr('id'));

    var tags = JSON.stringify(mQuery(el).val());

    Mautic.ajaxActionRequest('plugin:leuchtfeuerCompanyTags:addCompanyTags', {tags: tags}, function(response) {
        if (response.tags) {
            mQuery('#' + mQuery(el).attr('id')).html(response.tags);
            mQuery('#' + mQuery(el).attr('id')).trigger('chosen:updated');
        }

        Mautic.removeLabelLoadingIndicator();
    });
};