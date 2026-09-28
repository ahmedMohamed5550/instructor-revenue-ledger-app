<?php

return [
    // Platform's cut of every subscription payment, as a percentage.
    // The remainder is split among the instructors whose courses the
    // subscription grants access to (see AllocateSubscriptionRevenueAction).
    'platform_cut_percent' => env('PLATFORM_CUT_PERCENT', 30),
];
