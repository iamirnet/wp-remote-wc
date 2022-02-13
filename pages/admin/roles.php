<?php
function iamir_net_role()
{
    // gets the simple_role role object
    $role = get_role('administrator');

    // add a new capability
    $role->add_cap('iamir_net_manage', true);
}

// add simple_role capabilities, priority must be after the initial role definition
add_action('init', 'iamir_net_role', 11);
