<?php

namespace App\Modules\ChatbotRag\Services\Retrieval;

use App\Modules\ChatbotRag\Services\Text\TextAnalyzer;

/**
 * Words visitors use for the same thing, in English and Bangla. A question about a "washroom" must
 * find the facility the mall calls a "toilet", and a Bangla question must find text written in
 * English. The list is fixed and reviewed by hand; nothing is learnt from questions.
 */
class QueryExpander
{
    /**
     * Each line is one meaning. Single words only, lower case.
     */
    private const GROUPS = [
        ['toilet', 'washroom', 'restroom', 'bathroom', 'lavatory', 'wc', 'loo', 'টয়লেট', 'বাথরুম', 'ওয়াশরুম', 'শৌচাগার'],
        ['prayer', 'namaz', 'salah', 'salat', 'mosque', 'pray', 'নামাজ', 'নামাজের', 'মসজিদ'],
        ['atm', 'cashpoint', 'cash', 'এটিএম', 'বুথ'],
        ['lift', 'elevator', 'লিফট'],
        ['exit', 'gate', 'বাহির', 'প্রস্থান', 'গেট'],
        ['parking', 'park', 'carpark', 'garage', 'পার্কিং', 'গাড়ি'],
        ['hours', 'hour', 'timing', 'timings', 'time', 'times', 'schedule', 'open', 'opens', 'opening', 'close', 'closes', 'closing', 'closed', 'সময়', 'সময়সূচি', 'খোলা', 'খোলে', 'বন্ধ'],
        ['wifi', 'internet', 'ওয়াইফাই', 'ইন্টারনেট'],
        ['refund', 'return', 'returns', 'exchange', 'ফেরত', 'বদল'],
        ['price', 'prices', 'cost', 'costs', 'দাম', 'মূল্য'],
        ['shop', 'shops', 'store', 'stores', 'outlet', 'দোকান', 'দোকানে'],
        ['floor', 'level', 'storey', 'তলা', 'তলায়', 'ফ্লোর'],
        ['discount', 'discounts', 'offer', 'offers', 'sale', 'deal', 'deals', 'promotion', 'ছাড়', 'অফার'],
        ['drink', 'drinks', 'beverage', 'beverages', 'পানীয়', 'ড্রিংক'],
        ['coffee', 'কফি'],
        ['tea', 'চা'],
        ['juice', 'জুস', 'শরবত'],
        ['water', 'পানি'],
        ['food', 'meal', 'meals', 'eat', 'restaurant', 'lunch', 'dinner', 'খাবার', 'খাওয়া', 'রেস্টুরেন্ট'],
        ['medicine', 'medicines', 'pharmacy', 'drug', 'ওষুধ', 'ঔষধ', 'ফার্মেসি'],
        ['book', 'books', 'বই'],
        ['shoe', 'shoes', 'sneaker', 'sneakers', 'footwear', 'জুতা', 'জুতো'],
        ['clothes', 'clothing', 'cloth', 'dress', 'wear', 'garment', 'কাপড়', 'পোশাক', 'জামা'],
        ['phone', 'phones', 'mobile', 'smartphone', 'ফোন', 'মোবাইল'],
        ['charger', 'chargers', 'চার্জার'],
        ['lost', 'found', 'missing', 'হারানো', 'হারিয়ে'],
        ['smoking', 'smoke', 'cigarette', 'ধূমপান'],
        ['pet', 'pets', 'dog', 'dogs', 'cat', 'পোষা', 'কুকুর'],
        ['wheelchair', 'accessible', 'accessibility', 'disabled', 'stepfree', 'হুইলচেয়ার', 'প্রতিবন্ধী'],
        ['baby', 'babies', 'child', 'children', 'kid', 'kids', 'শিশু', 'বাচ্চা'],
        ['payment', 'pay', 'card', 'cards', 'bkash', 'nagad', 'পেমেন্ট', 'কার্ড', 'বিকাশ'],
        ['help', 'information', 'info', 'desk', 'helpdesk', 'সাহায্য', 'তথ্য'],
        ['contact', 'call', 'যোগাযোগ'],
        ['grocery', 'groceries', 'supermarket', 'মুদি', 'বাজার'],
        ['cosmetics', 'beauty', 'makeup', 'skincare', 'প্রসাধনী'],
        ['electronics', 'gadget', 'gadgets', 'ইলেকট্রনিক্স'],
        ['security', 'guard', 'safety', 'নিরাপত্তা'],
    ];

    /** @var array<string, list<string>>|null */
    private ?array $index = null;

    public function __construct(private TextAnalyzer $analyzer) {}

    /**
     * Other words with the same meaning; empty when the word is in no group.
     *
     * @param  string  $word  A word as TextAnalyzer returns it (lower case, normalised)
     * @return list<string>
     */
    public function alternatives(string $word): array
    {
        return array_values(array_filter($this->index()[$word] ?? [], fn (string $other): bool => $other !== $word));
    }

    /**
     * @return array<string, list<string>>
     */
    private function index(): array
    {
        if ($this->index === null) {
            $this->index = [];

            foreach (self::GROUPS as $group) {
                $group = array_map($this->analyzer->normalise(...), $group);

                foreach ($group as $word) {
                    $this->index[$word] = array_values(array_unique([...($this->index[$word] ?? []), ...$group]));
                }
            }
        }

        return $this->index;
    }
}
