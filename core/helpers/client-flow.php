<?php

const CLIENT_FLOW_CHATBOT_WEB     = 'web';
const CLIENT_FLOW_CHATBOT_CHANNEL = 'chatbot_channel';
const CLIENT_FLOW_AUTOMATION      = 'automation';

function resolveClientFlow(array $client): string
{
    return match ($client['service'] ?? null) {
        CLIENT_FLOW_CHATBOT_WEB     => CLIENT_FLOW_CHATBOT_WEB,
        CLIENT_FLOW_CHATBOT_CHANNEL => CLIENT_FLOW_CHATBOT_CHANNEL,
        CLIENT_FLOW_AUTOMATION      => CLIENT_FLOW_AUTOMATION,
        default                     => CLIENT_FLOW_UNKNOWN
    };
}