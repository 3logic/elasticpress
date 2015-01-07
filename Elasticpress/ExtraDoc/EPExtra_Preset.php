<?php
namespace Elasticpress\ExtraDoc;

use \Elastica\Document,
    \Elasticpress\EPMapper;

class EPExtra_Preset extends EPExtraDocument{

    public static $es_type = 'ep_preset';

    public static $main_field = 'preset_text';

}