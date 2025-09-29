<?php
use Clover\View\View;

function view(array $data = []): View {
    return new View($data);
}
