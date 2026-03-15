<?php
const FLOW_DESIGN = 'DESIGN_ORDER';
const FLOW_CHATBOT_WEB = 'CHATBOT_WEB';
const FLOW_CHATBOT_CHANNEL = 'CHATBOT_CHANNEL';
const FLOW_AUTOMATION = 'AUTOMATION_NOTIFICATION';

function resolveFlow(array $product): string {
    return match (true) {
        $product['category'] === 'desain' => FLOW_DESIGN,

        $product['category'] === 'automation'
            && $product['sub_category'] === 'chatbot'
            && $product['service'] === 'website'
                => FLOW_CHATBOT_WEB,

        $product['category'] === 'automation'
            && $product['sub_category'] === 'chatbot'
                => FLOW_CHATBOT_CHANNEL,

        $product['category'] === 'automation'
            && $product['sub_category'] === 'notification'
                => FLOW_AUTOMATION,

        default => throw new Exception('Flow tidak dikenali')
    };
}