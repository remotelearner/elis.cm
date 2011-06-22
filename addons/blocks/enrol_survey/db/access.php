<?php
$block_enrol_survey_capabilities = array(

        'block/enrol_survey:edit' => array(

            'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'legacy' => array(
                'admin' => CAP_ALLOW
            )
        ),

        'block/enrol_survey:take' => array(

            'riskbitmask' => RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'legacy' => array(
                'admin' => CAP_ALLOW
            )
        ),

    );
?>
