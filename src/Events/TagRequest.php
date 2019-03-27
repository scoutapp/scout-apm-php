<?php

namespace Scoutapm\Events;

class TagRequest extends Tag
{
    public function getEventArray(array &$parents): array
    {
        return [
            ['TagRequest' => [
                'request_id' => $this->requestId,
                'tag' => $this->tag,
                'value' => $this->value,
                'timestamp' => $this->timestamp,
            ]]
        ];
    }
}
