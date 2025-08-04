<?php

namespace MauticPlugin\LeuchtfeuerCompanyTagsBundle;

class LeuchtfeuerCompanyTagsEvents
{
    public const ON_CAMPAIGN_TRIGGER_ACTION = 'mautic.compaigntags.on_campaign_trigger_action';

    public const CAMPAIGN_TAG_POS_ADD_TAG = 'mautic.compaigntags.post_add_tag';

    public const CAMPAIGN_TAG_POS_REMOVE_TAG = 'mautic.compaigntags.post_remove_tag';

    public const COMPANY_TAG_UPDATE = 'mautic.compaigntags.company_tag_update';

    public const COMPANYTAG_COMPANY_POS_UPDATE = 'mautic.compaigntags.company_company_pos_update';

    public const COMPANY_POS_UPDATE = 'mautic.compaigntags.company_pos_update';

    public const COMPANY_POS_SAVE = 'mautic.compaigntags.company_pos_save';
}
