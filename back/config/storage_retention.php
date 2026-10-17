<?php

return [
    // Days a processed raw_sensor_events row stays in the hot table before moving to the ARCHIVE table.
    'raw_hot_days' => (int) env('RAW_HOT_DAYS', 14),
    // Days a published+delivered domain_event_outboxes row is kept before deletion.
    'domain_outbox_days' => (int) env('DOMAIN_OUTBOX_DAYS', 7),
    // Days an audit_logs row stays hot before moving to the ARCHIVE table.
    'audit_hot_days' => (int) env('AUDIT_HOT_DAYS', 90),
    'batch_size' => 1000,
];
