<?php

// How the admin writes numbers and dates (PLAN.md D-240): AdminLang::number() and
// AdminLang::date(). A language writes these its own way, as it writes its words.
return [
    'format.decimal' => ',',
    'format.thousands' => '.',
    // After a number: "45%". Some languages put a space before it.
    'format.percent' => ' %',
    // :day is the day of the month, :month its short name below, :m its number, :year the year.
    'format.date' => ':day. :m. :year.',
    'format.day' => ':day. :m.',
    'format.month.1' => 'sij',
    'format.month.2' => 'velj',
    'format.month.3' => 'ožu',
    'format.month.4' => 'tra',
    'format.month.5' => 'svi',
    'format.month.6' => 'lip',
    'format.month.7' => 'srp',
    'format.month.8' => 'kol',
    'format.month.9' => 'ruj',
    'format.month.10' => 'lis',
    'format.month.11' => 'stu',
    'format.month.12' => 'pro',
];
