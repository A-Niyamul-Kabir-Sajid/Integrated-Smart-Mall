<?php

namespace Database\Seeders;

use App\Modules\AccountsAccess\Models\User;
use App\Modules\ChatbotRag\Services\Evaluation\DemoEvaluationSet;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeService;
use App\Modules\ChatbotRag\Services\Knowledge\KnowledgeSyncService;
use App\Modules\MallShopDirectory\Models\Mall;
use Illuminate\Database\Seeder;

/**
 * Demonstration knowledge for the assistant of the demo mall: a visitor FAQ, the house rules and an
 * internal handbook that only administrators may read, then the mall's public records captured as
 * documents. All of it is invented for the demonstration. Requires DemoMallSeeder and
 * DemoAccountsSeeder (the documents are written as the demo mall administrator). Never run against
 * real data.
 */
class DemoKnowledgeSeeder extends Seeder
{
    public const SOURCE = 'Demonstration content written for the demo mall';

    public const FAQ = <<<'TEXT'
        Q: What are the opening hours of Niagara City Mall?
        A: The mall is open every day from 10:00 to 22:00. Some shops keep their own hours: the pharmacy is open 24 hours and the food court serves until 23:30.

        Q: What does parking cost?
        A: Parking is in the basement (B1). Cars pay BDT 30 for the first two hours and BDT 20 for every further hour. Motorcycles pay BDT 10 for the whole visit.

        Q: Is there free Wi-Fi?
        A: Yes. Connect to the network NCM-Guest. It is free for two hours a day and needs no password.

        Q: Where is the lost and found?
        A: Lost items are handed in at the Information Desk at the main entrance on the Ground Floor and are kept for 30 days.

        Q: Which payment methods are accepted?
        A: Every shop accepts cash. Most shops also accept debit and credit cards, bKash and Nagad. Ask the shop before you order.

        Q: Can I return something I bought?
        A: Each shop handles its own returns. A shop on this platform accepts a return request within 14 days of the purchase when you show the receipt.

        Q: Is the mall accessible with a wheelchair?
        A: Yes. Lift A reaches every floor and wheelchairs can be borrowed free of charge at the Information Desk. Step-free facilities are marked on the Facilities page.

        Q: Is there a place for babies?
        A: A baby care room is on the First Floor next to Little Steps. The Facilities page shows whether it is open today.
        TEXT;

    public const RULES = <<<'TEXT'
        # Smoking
        Smoking is not allowed anywhere inside the mall, including the car park. A smoking area is outside the Demo Road exit.

        # Pets
        Pets are not allowed inside the mall. Assistance animals are welcome.

        # Photography
        You may take photos for yourself. Commercial photo and video shoots need written permission from the mall office.

        # First aid and safety
        A first-aid kit and a trained first aider are at the Information Desk. In an emergency follow the exit signs and the instructions of the security staff.

        # Prayer room
        The prayer room on the First Floor is open during mall hours, with separate rooms for men and women.
        TEXT;

    public const HANDBOOK = <<<'TEXT'
        # Loading dock
        Deliveries use the loading dock on Demo Road between 07:00 and 10:00. The gate code word for drivers this quarter is BLUE HERON.

        # Cleaning rota
        Washrooms are cleaned every two hours and the food court tables every thirty minutes during opening hours.

        # Tenant fit-out
        Fit-out and repair work in shops is allowed only between 22:30 and 08:00 and needs a work permit from the mall office.
        TEXT;

    public function run(KnowledgeService $knowledge, KnowledgeSyncService $sync): void
    {
        $mall = Mall::query()->where('slug', DemoMallSeeder::MALL_SLUG)->firstOrFail();
        $administrator = User::query()->where('email', 'mall.admin@demo.test')->firstOrFail();

        foreach ([
            [DemoEvaluationSet::FAQ, 'faq', 'public', self::FAQ],
            [DemoEvaluationSet::RULES, 'mall_guide', 'public', self::RULES],
            [DemoEvaluationSet::HANDBOOK, 'admin_report', 'mall_admin', self::HANDBOOK],
        ] as [$title, $kind, $scope, $content]) {
            $knowledge->create($administrator, $mall, [
                'title' => $title, 'source_kind' => $kind, 'access_scope' => $scope, 'content' => $content, 'source_reference' => self::SOURCE,
            ]);
        }

        $sync->syncMall($mall);
    }
}
