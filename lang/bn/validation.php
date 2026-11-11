<?php

/**
 * Bangla validation messages for the rules the application uses. Rules not
 * listed here fall back to English (config app.fallback_locale).
 */
return [
    'array' => ':attribute একটি তালিকা হতে হবে।',
    'between' => [
        'numeric' => ':attribute :min থেকে :max এর মধ্যে হতে হবে।',
        'string' => ':attribute :min থেকে :max অক্ষরের মধ্যে হতে হবে।',
    ],
    'boolean' => ':attribute হ্যাঁ বা না হতে হবে।',
    'confirmed' => ':attribute নিশ্চিতকরণের সাথে মেলেনি।',
    'current_password' => 'পাসওয়ার্ড সঠিক নয়।',
    'different' => ':attribute এবং :other ভিন্ন হতে হবে।',
    'email' => 'সঠিক ইমেইল ঠিকানা দিন।',
    'exists' => 'বাছাই করা :attribute সঠিক নয়।',
    'file' => ':attribute একটি ফাইল হতে হবে।',
    'gte' => [
        'numeric' => ':attribute অন্তত :value হতে হবে।',
    ],
    'image' => ':attribute একটি ছবি হতে হবে।',
    'in' => 'বাছাই করা :attribute সঠিক নয়।',
    'integer' => ':attribute একটি পূর্ণসংখ্যা হতে হবে।',
    'max' => [
        'array' => ':attribute-এ সর্বোচ্চ :max টি আইটেম থাকতে পারে।',
        'file' => ':attribute সর্বোচ্চ :max কিলোবাইট হতে পারে।',
        'numeric' => ':attribute সর্বোচ্চ :max হতে পারে।',
        'string' => ':attribute সর্বোচ্চ :max অক্ষরের হতে পারে।',
    ],
    'mimes' => ':attribute এই ধরনের ফাইল হতে হবে: :values।',
    'min' => [
        'array' => ':attribute-এ অন্তত :min টি আইটেম থাকতে হবে।',
        'numeric' => ':attribute অন্তত :min হতে হবে।',
        'string' => ':attribute অন্তত :min অক্ষরের হতে হবে।',
    ],
    'numeric' => ':attribute একটি সংখ্যা হতে হবে।',
    'password' => [
        'letters' => ':attribute-এ অন্তত একটি অক্ষর থাকতে হবে।',
        'mixed' => ':attribute-এ অন্তত একটি বড় হাতের ও একটি ছোট হাতের অক্ষর থাকতে হবে।',
        'numbers' => ':attribute-এ অন্তত একটি সংখ্যা থাকতে হবে।',
        'symbols' => ':attribute-এ অন্তত একটি চিহ্ন থাকতে হবে।',
        'uncompromised' => 'এই :attribute একটি ফাঁস হওয়া তালিকায় পাওয়া গেছে। অন্য একটি বেছে নিন।',
    ],
    'regex' => ':attribute-এর গঠন সঠিক নয়।',
    'required' => ':attribute দেওয়া আবশ্যক।',
    'required_if' => ':other :value হলে :attribute দেওয়া আবশ্যক।',
    'required_without' => ':values না থাকলে :attribute দেওয়া আবশ্যক।',
    'string' => ':attribute লেখা হতে হবে।',
    'unique' => 'এই :attribute আগেই ব্যবহৃত হয়েছে।',
    'uuid' => ':attribute সঠিক নয়।',

    'attributes' => [
        'current_password' => 'বর্তমান পাসওয়ার্ড',
        'default_mall_id' => 'নিয়মিত মল',
        'display_name' => 'পুরো নাম',
        'email' => 'ইমেইল',
        'label' => 'আইটেম',
        'login' => 'ইমেইল বা মোবাইল নম্বর',
        'mall_id' => 'মল',
        'max_price' => 'সর্বোচ্চ দাম',
        'min_price' => 'সর্বনিম্ন দাম',
        'name' => 'নাম',
        'note' => 'নোট',
        'password' => 'পাসওয়ার্ড',
        'password_confirmation' => 'পাসওয়ার্ড নিশ্চিতকরণ',
        'phone' => 'মোবাইল নম্বর',
        'quantity' => 'পরিমাণ',
        'q' => 'খোঁজার শব্দ',
        'sort' => 'সাজানোর ধরন',
    ],
];
