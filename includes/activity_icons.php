<?php
function getActivityIcon($type) {
    switch ($type) {
        case 'login':
            return ['fas fa-sign-in-alt', 'bg-blue-100 text-blue-600'];
        case 'farmer':
            return ['fas fa-user-plus', 'bg-green-100 text-green-600'];
        case 'rsbsa':
            return ['fas fa-certificate', 'bg-yellow-100 text-yellow-600'];
        case 'yield':
            return ['fas fa-chart-bar', 'bg-purple-100 text-purple-600'];
        case 'commodity':
            return ['fas fa-wheat-awn', 'bg-orange-100 text-orange-600'];
        case 'input':
            return ['fas fa-boxes', 'bg-indigo-100 text-indigo-600'];
        default:
            return ['fas fa-check-circle', 'bg-gray-100 text-gray-600'];
    }
}
