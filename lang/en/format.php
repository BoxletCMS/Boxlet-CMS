<?php

// How the admin writes numbers and dates (PLAN.md D-240): AdminLang::number() and
// AdminLang::date(). A language writes these its own way, as it writes its words.
return [
    'format.decimal' => '.',
    'format.thousands' => ',',
    // After a number: "45%". Some languages put a space before it.
    'format.percent' => '%',
    // :day is the day of the month, :month its short name below, :m its number, :year the year.
    'format.date' => ':day :month :year',
    'format.day' => ':day :month',
    'format.month.1' => 'Jan',
    'format.month.2' => 'Feb',
    'format.month.3' => 'Mar',
    'format.month.4' => 'Apr',
    'format.month.5' => 'May',
    'format.month.6' => 'Jun',
    'format.month.7' => 'Jul',
    'format.month.8' => 'Aug',
    'format.month.9' => 'Sep',
    'format.month.10' => 'Oct',
    'format.month.11' => 'Nov',
    'format.month.12' => 'Dec',
];
