<?php

require_once __DIR__ . '/../config/env.php';
echo getenv('STRIPE_SECRET_KEY');
