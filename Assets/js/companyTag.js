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

    Mautic.ajaxActionRequest('plugin:LeuchtfeuerCompanyTags:addCompanyTags', {tags: tags}, function(response) {
        if (response.tags) {
            mQuery('#' + mQuery(el).attr('id')).html(response.tags);
            mQuery('#' + mQuery(el).attr('id')).trigger('chosen:updated');
        }

        Mautic.removeLabelLoadingIndicator();
    });
};

Mautic.removeCompanyCompanyTag = function (el) {
    mQuery(el).removeClass('fa-times').addClass('fa-spinner fa-spin');
    var companyId = mQuery(el).attr('data-company-id');
    var tagId = mQuery(el).attr('data-tag-id');
    Mautic.ajaxActionRequest('plugin:LeuchtfeuerCompanyTags:removeCompanyCompanyTag',
        {
            tagId: tagId,
            companyId:companyId
        }, function(response) {
        if (response.success == 1) {
            mQuery(el).parent('span').fadeOut(300, function() { mQuery(this).remove(); });
        }
    });
};

Mautic.companyTagBatchSubmit = function() {
    if (Mautic.batchActionPrecheck()) {

        if (mQuery('#companytag_batch_remove').val() || mQuery('#companytag_batch_add').val()) {
            const ids = Mautic.getCheckedListIds(false, true);

            if (mQuery('#companytag_batch_ids').length) {
                mQuery('#companytag_batch_ids').val(ids);
            }

            return true;
        }

    }

    mQuery('#MauticSharedModal').modal('hide');

    return false;
};