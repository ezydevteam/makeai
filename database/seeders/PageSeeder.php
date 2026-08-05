<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * The four policy pages below ship as complete, readable documents rather than the
         * three-sentence stubs they used to be, because an empty Privacy Policy is the first
         * thing a buyer's own customers notice — and a page they have to write from nothing
         * is a page that stays unwritten.
         *
         * They are TEMPLATES describing how this platform actually behaves (credits, AI
         * providers, the cookie banner, account deletion), written to be edited rather than
         * published as-is. They are not legal advice, and no company name, jurisdiction or
         * retention period in them has been chosen for any particular operator: whoever runs
         * the site is expected to review the whole set with a lawyer and fill in the details
         * their own business and law require.
         *
         * Contact details are deliberately never invented — every page routes the reader to
         * the site's own /contact page instead of a made-up address.
         */
        $updated = now()->format('F j, Y');

        $pages = [
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => '<p><em>Last updated: ' . $updated . '</em></p>'
                    . '<p>This policy explains what MakeAI collects when you use the platform, why we collect it, who it is shared with, and the choices you have. It covers the website, the AI tools, the API and any account you hold with us.</p>'
                    . '<h2>1. Information We Collect</h2>'
                    . '<h3>Information you give us</h3>'
                    . '<ul>'
                    . '<li><strong>Account details</strong> — your name, email address and password. If you sign in through a social provider, we receive the name, email address and profile picture that provider shares with us.</li>'
                    . '<li><strong>Content you submit</strong> — the prompts, documents, files and settings you send to the AI tools, and the output they return.</li>'
                    . '<li><strong>Billing information</strong> — your plan, invoices and payment status. Card numbers are entered on the payment provider\'s systems and never reach ours.</li>'
                    . '<li><strong>Support messages</strong> — anything you write to us through the contact form, a support ticket or email.</li>'
                    . '</ul>'
                    . '<h3>Information collected automatically</h3>'
                    . '<ul>'
                    . '<li><strong>Usage data</strong> — which tools you open, how many credits a request consumed, and which model handled it.</li>'
                    . '<li><strong>Technical data</strong> — IP address, browser and device type, language and the pages you visit.</li>'
                    . '<li><strong>Cookies</strong> — described in full in our <a href="/cookie-policy">Cookie Policy</a>.</li>'
                    . '</ul>'
                    . '<h2>2. How We Use Your Information</h2>'
                    . '<p>We use what we collect to:</p>'
                    . '<ul>'
                    . '<li>run your account, process generations and meter credits;</li>'
                    . '<li>take payment, issue invoices and manage subscriptions;</li>'
                    . '<li>answer support requests and send service notices about your account;</li>'
                    . '<li>detect abuse, fraud and attempts to bypass usage limits;</li>'
                    . '<li>understand which features are used so we know what to improve;</li>'
                    . '<li>send marketing email where you have asked for it, which you can stop at any time.</li>'
                    . '</ul>'
                    . '<h2>3. AI Processing and Model Providers</h2>'
                    . '<p>Generating content means sending your prompt, and any file or context attached to it, to the AI provider that serves the model you selected. Those providers process the request on our behalf and return the result.</p>'
                    . '<p>We do not use your prompts or generated content to train AI models. Each provider applies its own retention and processing terms to requests it receives, and we choose providers whose terms match this policy.</p>'
                    . '<h2>4. Legal Bases for Processing</h2>'
                    . '<p>Where data protection law requires a legal basis, we rely on:</p>'
                    . '<ul>'
                    . '<li><strong>Contract</strong> — to provide the service you signed up for and to bill you for it.</li>'
                    . '<li><strong>Legitimate interests</strong> — to keep the platform secure, prevent abuse and improve what we offer.</li>'
                    . '<li><strong>Consent</strong> — for optional cookies and marketing email, which you can withdraw at any time.</li>'
                    . '<li><strong>Legal obligation</strong> — to keep tax and accounting records.</li>'
                    . '</ul>'
                    . '<h2>5. Sharing Your Information</h2>'
                    . '<p>We do not sell your personal data. We share it only with:</p>'
                    . '<ul>'
                    . '<li><strong>Service providers</strong> who process it for us — AI model providers, payment gateways, email delivery, hosting and analytics — each bound to use it only for the service they supply.</li>'
                    . '<li><strong>Authorities</strong>, where the law requires it or to protect our rights, users or platform.</li>'
                    . '<li><strong>A successor</strong>, if the business is merged or acquired. You will be told before your data becomes subject to a different policy.</li>'
                    . '</ul>'
                    . '<h2>6. Data Retention</h2>'
                    . '<p>Account data is kept while your account is open. Generated documents and chat history stay until you delete them or close your account. Usage and billing records are held for as long as tax and accounting rules require. When an account is deleted, personal data is removed or anonymised, apart from records we are legally obliged to keep.</p>'
                    . '<h2>7. Your Rights</h2>'
                    . '<p>Depending on where you live, you may have the right to:</p>'
                    . '<ul>'
                    . '<li>see the personal data we hold about you and get a copy of it;</li>'
                    . '<li>correct anything inaccurate;</li>'
                    . '<li>delete your account and the data attached to it;</li>'
                    . '<li>export your content in a portable format;</li>'
                    . '<li>object to, or ask us to restrict, certain processing;</li>'
                    . '<li>withdraw consent for cookies or marketing at any time.</li>'
                    . '</ul>'
                    . '<p>Most of these are available directly in your account settings. For anything else, reach us through the <a href="/contact">contact page</a>.</p>'
                    . '<h2>8. Security</h2>'
                    . '<p>Passwords are hashed, API keys and payment credentials are encrypted at rest, and traffic runs over TLS. Optional two-factor authentication is available on every account. No system is perfectly secure, so we also limit who on our side can reach production data, and we will notify you and the relevant authority if a breach affects you.</p>'
                    . '<h2>9. International Transfers</h2>'
                    . '<p>Our providers may process data in countries other than yours. Where that happens we rely on transfer mechanisms recognised by applicable law, such as standard contractual clauses.</p>'
                    . '<h2>10. Children</h2>'
                    . '<p>The platform is not intended for anyone under 16, and we do not knowingly collect their data. If you believe a child has created an account, tell us and we will remove it.</p>'
                    . '<h2>11. Changes to This Policy</h2>'
                    . '<p>We will post any update on this page and change the date at the top. Where a change materially affects you, we will tell you before it takes effect.</p>'
                    . '<h2>12. Contact Us</h2>'
                    . '<p>Questions about this policy, or about the data we hold on you, can be sent through our <a href="/contact">contact page</a>.</p>',
                'excerpt' => 'What we collect, why we collect it, who it is shared with, and the control you have over it.',
                'meta_description' => 'How MakeAI collects, uses, shares and protects personal data — including how prompts are handled by AI providers, and the rights you can exercise over your account.',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'content' => '<p><em>Last updated: ' . $updated . '</em></p>'
                    . '<p>These terms are the agreement between you and MakeAI for use of the platform. By creating an account or using any part of the service, you accept them. If you are agreeing on behalf of a company, you confirm you are allowed to bind it.</p>'
                    . '<h2>1. The Service</h2>'
                    . '<p>MakeAI provides AI-assisted tools for generating and editing content, together with the account, billing and support around them. We may add, change or retire individual tools and models as the underlying technology moves, and we will not remove a material feature of a paid plan without notice.</p>'
                    . '<h2>2. Accounts</h2>'
                    . '<ul>'
                    . '<li>You must be at least 16, or the age of digital consent where you live, whichever is higher.</li>'
                    . '<li>Give accurate registration details and keep them current.</li>'
                    . '<li>You are responsible for everything done under your account, so keep your password private and enable two-factor authentication if the account matters to you.</li>'
                    . '<li>One account per person or organisation. Sharing a single account across a team is not a substitute for buying seats.</li>'
                    . '<li>Tell us as soon as you suspect unauthorised access.</li>'
                    . '</ul>'
                    . '<h2>3. Plans, Credits and Billing</h2>'
                    . '<p>Paid plans are billed in advance for the period you choose and renew automatically until cancelled. Prices are shown before you pay and exclude any tax we are required to add.</p>'
                    . '<ul>'
                    . '<li><strong>Credits</strong> — generations consume credits at a rate that depends on the model and the length of the request. Your balance and the cost of each run are visible in your account.</li>'
                    . '<li><strong>Renewal</strong> — plan credits refresh each billing period. Unless your plan says otherwise, unused plan credits do not roll over. Credits bought as a top-up are kept separately and are not reset by a renewal.</li>'
                    . '<li><strong>Cancellation</strong> — cancel at any time from your billing settings. You keep access until the end of the period you have already paid for.</li>'
                    . '<li><strong>Failed payments</strong> — if a renewal fails we may retry it and, after notice, suspend paid features until the balance is settled.</li>'
                    . '<li><strong>Price changes</strong> — we will tell you before a price change affects a renewal, so you can cancel first.</li>'
                    . '</ul>'
                    . '<h2>4. Refunds</h2>'
                    . '<p>Credits that have been spent on a generation cannot be refunded, because the cost has already been incurred with the model provider. If the service failed on our side, or you were charged in error, contact us and we will put it right. Statutory rights to a refund or cooling-off period are unaffected by anything in this section.</p>'
                    . '<h2>5. Acceptable Use</h2>'
                    . '<p>What you may and may not generate is set out in our <a href="/usage-policy">Usage Policy</a>, which forms part of these terms. In short: create lawful, original work, do not use the platform to harm anyone, and do not attempt to break, overload or circumvent the limits it runs under.</p>'
                    . '<h2>6. Your Content</h2>'
                    . '<p>You keep ownership of the prompts, files and documents you bring to the platform. You grant us only the licence we need to run the service — to store your content, transmit it to the model provider handling your request, and display it back to you. That licence ends when you delete the content or close your account.</p>'
                    . '<p>You confirm you have the rights to whatever you upload, and that processing it here does not breach anyone else\'s rights.</p>'
                    . '<h2>7. AI Output</h2>'
                    . '<p>Subject to these terms and to the model provider\'s own conditions, output you generate is yours to use, including commercially. Three things are worth knowing before you rely on it:</p>'
                    . '<ul>'
                    . '<li>AI output can be wrong, outdated or biased. Check anything you intend to publish or act on.</li>'
                    . '<li>Similar prompts can produce similar results for different users, so output is not guaranteed to be unique and may not be protectable in every jurisdiction.</li>'
                    . '<li>You are responsible for what you publish, including whether it complies with the law and with the rules of any platform you post it to.</li>'
                    . '</ul>'
                    . '<h2>8. Our Intellectual Property</h2>'
                    . '<p>The platform itself — the software, interface, branding and documentation — belongs to us and our licensors. These terms give you a limited, non-exclusive, non-transferable right to use it while your account is active, and nothing more. Do not copy, resell, reverse engineer or create a competing service from it.</p>'
                    . '<h2>9. Third-Party Services</h2>'
                    . '<p>Payment gateways, AI providers, analytics and other integrations are operated by third parties under their own terms. We are not responsible for their services, and their availability can affect ours.</p>'
                    . '<h2>10. API and Automated Access</h2>'
                    . '<p>Where API access is part of your plan, keep your keys secret, stay inside the documented rate limits, and do not use the API to rebuild the platform for others. We may throttle or suspend a key that threatens the stability of the service.</p>'
                    . '<h2>11. Affiliate Program</h2>'
                    . '<p>If you join the affiliate program, commission is earned on qualifying referrals under the terms shown in your affiliate dashboard. Self-referrals, misleading advertising, unsolicited email, brand-name bidding and cookie stuffing forfeit commission and can close the account.</p>'
                    . '<h2>12. Suspension and Termination</h2>'
                    . '<p>You can close your account at any time. We may suspend or close an account that breaches these terms or the Usage Policy, that puts the platform or other users at risk, or that goes unpaid. Where the circumstances allow, we will give notice and a chance to fix the problem first. On closure your right to use the service ends; export anything you want to keep beforehand.</p>'
                    . '<h2>13. Disclaimers</h2>'
                    . '<p>The service is provided "as is" and "as available". To the extent the law allows, we make no warranty that it will be uninterrupted, error-free, or fit for a particular purpose, and we do not warrant the accuracy of AI output.</p>'
                    . '<h2>14. Limitation of Liability</h2>'
                    . '<p>To the maximum extent permitted by law, we are not liable for indirect, incidental, special or consequential loss, nor for lost profits, revenue, goodwill or data. Our total liability for any claim relating to the service is limited to the amount you paid us in the twelve months before the claim arose. Nothing here excludes liability that cannot lawfully be excluded.</p>'
                    . '<h2>15. Indemnity</h2>'
                    . '<p>You agree to cover our reasonable costs if a third party brings a claim against us because of content you submitted or generated, or because you used the service in breach of these terms.</p>'
                    . '<h2>16. Changes to These Terms</h2>'
                    . '<p>We may update these terms as the service changes. The date at the top will change, and material updates will be announced in advance. Continuing to use the platform after an update means you accept it.</p>'
                    . '<h2>17. Governing Law</h2>'
                    . '<p>These terms are governed by the laws of the jurisdiction in which MakeAI is established, without regard to conflict-of-law rules, and the courts of that jurisdiction have exclusive jurisdiction over any dispute. Consumers keep the protection of the mandatory law of their own country of residence.</p>'
                    . '<h2>18. Contact</h2>'
                    . '<p>Questions about these terms can be sent through our <a href="/contact">contact page</a>.</p>',
                'excerpt' => 'The agreement between you and MakeAI: accounts, plans and credits, what you may generate, and who is responsible for what.',
                'meta_description' => 'The terms governing use of MakeAI — accounts, billing and credits, refunds, ownership of AI output, acceptable use, and the limits of our liability.',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact',
                'content' => '<h2>We\'re Here to Help</h2><p>Have a question about our features, pricing, or anything else? Our team is ready to answer all your questions.</p>',
                'excerpt' => 'Get in touch with the MakeAI support team.',
                'status' => 'published',
                'is_system' => true,
            ],
            /*
             * The About page is the one CMS page a buyer shows a client, so it ships with a
             * full narrative rather than two placeholder paragraphs.
             *
             * The markup is plain rich text on purpose — headings, paragraphs, one quote and
             * two lists — because that is exactly what the admin editor round-trips without
             * loss. AboutPage.vue reads the structure and does the design: each <h2> opens a
             * section and joins the sticky index, the bold-led <ul> becomes a card grid, and
             * the bold-led <ol> becomes the milestone timeline. Rewriting this copy in the
             * admin keeps every one of those treatments.
             */
            [
                'title' => 'About Us',
                'slug' => 'about',
                'content' => '<p>MakeAI started with a small, stubborn observation: the hard part of creating was never the typing. It was the blank page, the tenth revision, the three hours spent making something sound like you. So we built a workspace where the machine handles the drafting and the person keeps the judgement.</p>'
                    . '<h2>Our mission</h2>'
                    . '<p>We want advanced AI to be something a freelancer can use on a Tuesday afternoon, not a capability reserved for teams with an engineering budget. That means no prompt engineering courses, no API keys to juggle, and no pricing that punishes you for experimenting.</p>'
                    . '<p>Everything on the platform is built around one question: does this get someone from an idea to a finished piece of work faster, without making the work worse?</p>'
                    . '<blockquote>Good tools disappear. You should be thinking about what you are making, not about the thing you are making it with.</blockquote>'
                    . '<h2>What we believe</h2>'
                    . '<ul>'
                    . '<li><strong>Output beats output volume</strong> — Generating a hundred drafts is easy. We optimise for the one you would actually publish.</li>'
                    . '<li><strong>You own what you make</strong> — Your prompts, your documents and your brand voice belong to you, and you can export all of it at any time.</li>'
                    . '<li><strong>No black boxes</strong> — Every generation shows which model ran it and what it cost, so nothing about your usage is a surprise at the end of the month.</li>'
                    . '<li><strong>The best model changes weekly</strong> — So we stay provider-agnostic and route each tool to whichever model does that job best today.</li>'
                    . '<li><strong>Privacy is a default, not a plan tier</strong> — We do not train on your content, and we never have.</li>'
                    . '<li><strong>Speed is a feature</strong> — A tool you wait on is a tool you stop opening. Latency is treated as a bug.</li>'
                    . '</ul>'
                    . '<h2>How we got here</h2>'
                    . '<ol>'
                    . '<li><strong>2023</strong> — Two people, one writing assistant, and a spreadsheet of everyone who asked to try it.</li>'
                    . '<li><strong>2024</strong> — The template library grew past fifty tools and the first agencies moved their whole content workflow across.</li>'
                    . '<li><strong>2025</strong> — Images, chat and document analysis joined the text tools, and brand voice made the output finally sound like the customer.</li>'
                    . '<li><strong>2026</strong> — Teams, shared workspaces and an API, so the platform fits into work that was already running elsewhere.</li>'
                    . '</ol>'
                    . '<h2>How we work</h2>'
                    . '<p>We are a small, remote team spread across four time zones, and we ship in small pieces rather than in launches. Most of what we build starts as a support conversation — someone describes a workflow that takes them an hour, and we go and find the fifty minutes.</p>'
                    . '<p>We answer our own support tickets. Every engineer here has read a frustrated message about their own feature, which turns out to be the fastest product research there is.</p>'
                    . '<h2>Where we are going</h2>'
                    . '<p>The next few years of this are less about bigger models and more about better context: an assistant that knows your audience, your past work and your constraints, and stops asking you to explain them again every morning.</p>'
                    . '<p>If that is the kind of tool you want to use — or help build — we would like to hear from you.</p>',
                'excerpt' => 'We build the workspace where ideas turn into finished work — a small team making advanced AI simple enough to use on an ordinary Tuesday afternoon.',
                'meta_title' => 'About MakeAI — the team behind the AI workspace',
                'meta_description' => 'The story, the principles and the people behind MakeAI: why we build AI tools that stay out of the way and let you keep the judgement.',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Frequently Asked Questions',
                'slug' => 'faq',
                'content' => '<h3>What is MakeAI?</h3><p>MakeAI is an all-in-one platform for generative AI, offering tools for text generation, image creation, and more.</p><h3>Is there a free trial?</h3><p>Yes, we offer a free plan with limited credits to help you get started.</p><h3>How do I cancel my subscription?</h3><p>You can cancel your subscription at any time from your account settings.</p>',
                'excerpt' => 'Find answers to the most common questions about MakeAI.',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Usage Policy',
                'slug' => 'usage-policy',
                'content' => '<p><em>Last updated: ' . $updated . '</em></p>'
                    . '<p>This policy sets out what you may and may not do with the AI tools. It forms part of our <a href="/terms-of-service">Terms of Service</a> and applies to everyone using the platform, through the web app or the API.</p>'
                    . '<h2>1. Acceptable Use</h2>'
                    . '<p>The tools are here to help you create original, lawful work. You are responsible for the prompts you submit, the files you upload and anything you publish from the output — reviewing it before it goes anywhere is part of using the platform properly.</p>'
                    . '<h2>2. Prohibited Content</h2>'
                    . '<p>Do not use the platform to produce, upload or distribute content that:</p>'
                    . '<ul>'
                    . '<li>breaks the law, or promotes or facilitates unlawful activity;</li>'
                    . '<li>sexualises minors in any way, or is sexual material involving real people who have not consented;</li>'
                    . '<li>harasses, threatens, defames or incites violence or hatred against a person or group;</li>'
                    . '<li>gives instructions for weapons, explosives, malware or attacks on computer systems;</li>'
                    . '<li>impersonates a real person or organisation, or fabricates statements, endorsements, reviews or records presented as genuine;</li>'
                    . '<li>spreads deliberate misinformation about health, elections or public safety;</li>'
                    . '<li>presents itself as professional medical, legal or financial advice without qualified human review;</li>'
                    . '<li>infringes copyright, trademarks, trade secrets, privacy or publicity rights;</li>'
                    . '<li>is designed to deceive — phishing pages, fake storefronts, scam messaging or fraudulent offers;</li>'
                    . '<li>is bulk unsolicited email, spam or content generated purely to manipulate search rankings.</li>'
                    . '</ul>'
                    . '<h2>3. Platform Abuse</h2>'
                    . '<p>Regardless of what is being generated, do not:</p>'
                    . '<ul>'
                    . '<li>work around credit costs, rate limits, plan restrictions or the licence check;</li>'
                    . '<li>share one account between multiple people to avoid paying for seats, or resell access as your own service;</li>'
                    . '<li>scrape the site, or automate the interface in place of the documented API;</li>'
                    . '<li>probe, scan or stress the infrastructure, or attempt to reach data belonging to another account;</li>'
                    . '<li>upload malware, or use the platform as a host for files unrelated to your own work;</li>'
                    . '<li>prompt the models with the aim of extracting system instructions or bypassing their safety behaviour.</li>'
                    . '</ul>'
                    . '<h2>4. Credits and Fair Use</h2>'
                    . '<p>Each generation consumes credits according to the model chosen and the length of the request, and the cost is shown alongside the result. Daily and monthly limits may apply to your plan. Accounts that repeatedly exceed fair-use thresholds, or whose traffic pattern degrades the service for others, may be rate limited while we get in touch.</p>'
                    . '<h2>5. Files, Uploads and Third-Party Data</h2>'
                    . '<p>Only upload documents you are entitled to process. If a file contains someone else\'s personal data, you are the controller of that data and are responsible for having a lawful basis to run it through the platform.</p>'
                    . '<h2>6. Ownership and Review of Output</h2>'
                    . '<p>Subject to the Terms of Service, output you generate belongs to you. It may not be unique, it can be factually wrong, and it can reflect bias present in the training data. Review it before commercial use, and disclose that content is AI-assisted where your audience or the law expects it.</p>'
                    . '<h2>7. Reporting Abuse</h2>'
                    . '<p>If you come across content or an account that breaks this policy, tell us through the <a href="/contact">contact page</a> with enough detail for us to find it.</p>'
                    . '<h2>8. Enforcement</h2>'
                    . '<p>Depending on what happened and how serious it is, we may remove content, restrict a feature, rate limit an account, suspend it, or close it and refuse further service. Serious cases may be reported to the authorities. Where the circumstances allow, we give notice and a chance to put things right first, and you can ask us to review any decision through the contact page.</p>'
                    . '<h2>9. Changes to This Policy</h2>'
                    . '<p>As the tools and the models behind them change, so will this policy. Updates appear on this page with a new date at the top.</p>',
                'excerpt' => 'What you may and may not create with the AI tools, how credits and fair use work, and what happens when the rules are broken.',
                'meta_description' => 'Acceptable use of the MakeAI platform: prohibited content, platform abuse, credits and fair-use limits, ownership of output, and how the policy is enforced.',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Cookie Policy',
                'slug' => 'cookie-policy',
                'content' => '<p><em>Last updated: ' . $updated . '</em></p>'
                    . '<p>This policy explains the cookies and similar technologies MakeAI uses, what each kind is for, and how to change your mind. It sits alongside our <a href="/privacy-policy">Privacy Policy</a>.</p>'
                    . '<h2>1. What Cookies Are</h2>'
                    . '<p>A cookie is a small text file a site stores on your device so it can recognise you on your next request. We also use related technologies — local storage, session storage and small tracking pixels in email — and treat them the same way in this policy.</p>'
                    . '<h2>2. Why We Use Them</h2>'
                    . '<p>Cookies keep you signed in between pages, remember the choices you make, protect forms against cross-site request forgery, and tell us which parts of the platform are actually used. Without the essential ones the site cannot work.</p>'
                    . '<h2>3. The Categories We Use</h2>'
                    . '<h3>Strictly necessary</h3>'
                    . '<p>Session and authentication cookies, the CSRF token, the cookie-consent record itself, and load-balancing cookies. These cannot be switched off, because the site would stop functioning without them.</p>'
                    . '<h3>Preferences</h3>'
                    . '<p>Your language, light or dark theme, sidebar state and dismissed notices. They only make the site behave the way you left it.</p>'
                    . '<h3>Analytics</h3>'
                    . '<p>Aggregate measurement of visits, page views and which features are used, so we know what to improve. These are set only where you allow them, and where an analytics integration is enabled on this site.</p>'
                    . '<h3>Marketing</h3>'
                    . '<p>Set by advertising or affiliate partners to attribute a signup to the campaign or referral link that produced it. These are set only where you allow them.</p>'
                    . '<h2>4. Third-Party Cookies</h2>'
                    . '<p>Some cookies are set by services we rely on rather than by us — the payment gateway during checkout, an embedded video player, or an analytics provider. Those are governed by the provider\'s own policy, and we have no access to what they store.</p>'
                    . '<h2>5. How Long They Last</h2>'
                    . '<p>Session cookies disappear when you close the browser. Persistent cookies stay until they expire or you clear them: preference cookies typically last up to a year, and your consent choice is remembered for the same period so you are not asked on every visit.</p>'
                    . '<h2>6. Managing Your Choices</h2>'
                    . '<p>When cookie consent is enabled on this site, a banner appears on your first visit and you can accept everything, keep only what is necessary, or choose category by category. You can reopen those settings at any time from the link in the footer.</p>'
                    . '<p>Your browser can also block or delete cookies for any site. Blocking the strictly necessary ones will sign you out and break parts of the platform.</p>'
                    . '<h2>7. Do Not Track</h2>'
                    . '<p>Browsers send "Do Not Track" signals in inconsistent ways and there is no agreed standard for answering them, so we do not respond to the header. The consent controls above are the reliable way to tell us what you want.</p>'
                    . '<h2>8. Changes to This Policy</h2>'
                    . '<p>If the cookies we use change, this page is updated and the date at the top changes with it.</p>'
                    . '<h2>9. Contact</h2>'
                    . '<p>Questions about how we use cookies can be sent through our <a href="/contact">contact page</a>.</p>',
                'excerpt' => 'The cookies this site sets, what each category is for, how long they last, and how to change your choices.',
                'meta_description' => 'The cookies MakeAI uses — strictly necessary, preferences, analytics and marketing — how long each lasts, and how to manage your consent.',
                'status' => 'published',
                'is_system' => true,
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'show_title' => true,
                    'show_breadcrumbs' => true,
                    'show_featured_image' => false,
                    'show_sidebar' => false,
                    'sidebar_position' => 'left',
                    'container_width' => 'default',
                    'published_at' => now(),
                ])
            );
        }
    }
}
