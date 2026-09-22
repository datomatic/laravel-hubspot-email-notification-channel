<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\CouldNotSendNotification;
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\InvalidConfiguration;
use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ChannelFeatureTest extends TestCase
{
    /** @var HubspotEmailChannel */
    protected $channel;

    protected function getPackageProviders($app)
    {
        return ['Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailServiceProvider'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new HubspotEmailChannel;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function configSetUp()
    {
        $this->app['config']->set('mail.from.address', 'from@email.com');
        $this->app['config']->set('mail.from.name', 'from_name');
        $this->app['config']->set('hubspot.api_key', 'testApiKey');
        $this->app['config']->set('hubspot.hubspot_owner_id', '2342345234434');
    }

    private function mockHubspotResponse()
    {
        $this->configSetUp();
        Http::fake(function ($request) {
            if (strpos($request->url(), '/associations/email/') !== false
                && strpos($request->url(), HubspotEmailChannel::HUBSPOT_URL_V4) !== false
            ) {
                $path = parse_url($request->url(), PHP_URL_PATH);
                preg_match('#/objects/\w+/(\d+)/associations/email/(\d+)#', $path, $matches);
                [, $hubspotContactId, $hubspotEmailId] = $matches;

                return Http::response(json_encode([
                    'status' => 'COMPLETE',
                    'results' => [
                        [
                            'from' => [
                                'id' => $hubspotEmailId,
                            ],
                            'to' => [
                                'id' => $hubspotContactId,
                            ],
                            'associationSpec' => [
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 198,
                            ],
                        ],
                        [
                            'from' => [
                                'id' => $hubspotContactId,
                            ],
                            'to' => [
                                'id' => $hubspotEmailId,
                            ],
                            'associationSpec' => [
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 197,
                            ],
                        ],
                    ],
                    'startedAt' => round(microtime(true) * 1000),
                    'completedAt' => round(microtime(true) * 1000),
                ]), 200, ['Content-Type: application/json']);
            } elseif (strpos($request->url(), 'emails') !== false
                && strpos($request->url(), HubspotEmailChannel::HUBSPOT_URL_V3) !== false
            ) {
                $data = $request->data()['properties'];

                return Http::response('{
    "id": "18339394130",
    "properties": {
        "hs_all_owner_ids": "'.$data['hubspot_owner_id'].'",
        "hs_body_preview": "Thanks for your interest let\'s find a time to connect",
        "hs_body_preview_html": "Thanks for your interest let\'s find a time to connect",
        "hs_body_preview_is_truncated": "false",
        "hs_createdate": "'.round(microtime(true) * 1000).'",
        "hs_email_attached_video_opened": "false",
        "hs_email_attached_video_watched": "false",
        "hs_email_direction": "EMAIL",
        "hs_email_status": "SENT",
        "hs_email_subject": "'.str_replace('"', '\"', $data['hs_email_subject']).'",
        "hs_email_text": "'.preg_replace("/\r|\n/", '', str_replace('"', '\"', $data['hs_email_text'])).'",
        "hs_lastmodifieddate": "'.round(microtime(true) * 1000).'",
        "hs_object_id": "18339394130",
        "hs_timestamp": "'.$data['hs_timestamp'].'",
        "hubspot_owner_assigneddate": "'.round(microtime(true) * 1000).'",
        "hubspot_owner_id": "'.$data['hubspot_owner_id'].'"
    },
    "createdAt": "'.round(microtime(true) * 1000).'",
    "updatedAt": "'.round(microtime(true) * 1000).'",
    "archived": false}', 201, ['Content-Type: application/json']);
            } elseif (strpos($request->url(), HubspotEmailChannel::HUBSPOT_URL_V3.'contacts/') === 0) {
                return Http::response(json_encode([
                    'id' => '987654321',
                    'properties' => [
                        'associatedcompanyid' => '9876',
                    ],
                ]), 200, ['Content-Type: application/json']);
            } else {
                return Http::response('{}', 200, ['Content-Type: application/json']);
            }
        });
    }

    private function mockHubspotErrorRequest()
    {
        $this->configSetUp();
        Http::fake(['*' => Http::response('Error', 404)]);
    }

    private function invalidAssociationResponse(string $objectType, string $objectId)
    {
        return Http::response(json_encode([
            'status' => 'error',
            'message' => 'One or more associations are invalid',
            'correlationId' => '01a0c99e-472a-73e4-b997-3a91989f63c9',
            'context' => [
                'INVALID_OBJECT_IDS' => [$objectType.'='.$objectId.' is not valid'],
                'objectId' => [$objectId],
                'objectType' => [$objectType],
            ],
            'category' => 'VALIDATION_ERROR',
        ]), 400, ['Content-Type: application/json']);
    }

    #[Test]
    public function it_throws_an_exception_when_it_is_not_configured()
    {
        Config::set('hubspot', null);
        $this->expectException(InvalidConfiguration::class);

        (new TestNotifiable)->notify(new TestLineMailNotification);
    }

    #[Test]
    public function it_throws_an_exception_when_it_could_not_send_the_notification()
    {
        $this->mockHubspotErrorRequest();
        $this->expectException(CouldNotSendNotification::class);

        $this->channel->send(new TestNotifiable, new TestLineMailNotification);
    }

    #[Test]
    public function it_does_not_leak_the_api_key_in_the_exception_message()
    {
        $this->mockHubspotErrorRequest();

        try {
            $this->channel->send(new TestNotifiable, new TestLineMailNotification);
            $this->fail('Expected CouldNotSendNotification to be thrown.');
        } catch (CouldNotSendNotification $e) {
            $this->assertStringNotContainsString(config('hubspot.api_key'), $e->getMessage());
            $this->assertStringNotContainsString('hapikey', $e->getMessage());
        }
    }

    #[Test]
    public function it_exposes_the_hubspot_validation_context_when_the_contact_id_is_invalid()
    {
        $this->configSetUp();
        Http::fake([
            HubspotEmailChannel::HUBSPOT_URL_V4.'*' => $this->invalidAssociationResponse('CONTACT', '838442890479'),
            '*' => Http::response(['id' => '18339394130'], 201, ['Content-Type: application/json']),
        ]);

        try {
            $this->channel->send(new TestNotifiable, new TestLineMailNotification);
            $this->fail('Expected CouldNotSendNotification to be thrown.');
        } catch (CouldNotSendNotification $e) {
            $this->assertTrue($e->hasInvalidObjectIds());
            $this->assertSame(['CONTACT=838442890479 is not valid'], $e->invalidObjectIds());
            $this->assertSame('VALIDATION_ERROR', $e->category());
            $this->assertSame('01a0c99e-472a-73e4-b997-3a91989f63c9', $e->correlationId());
        }
    }

    #[Test]
    public function it_still_sends_the_notification_when_only_the_company_association_is_invalid()
    {
        $this->configSetUp();
        $this->app['config']->set('hubspot.company_email_associations', true);
        Http::fake([
            HubspotEmailChannel::HUBSPOT_URL_V4.'company/*' => $this->invalidAssociationResponse('COMPANY', '9876'),
            HubspotEmailChannel::HUBSPOT_URL_V4.'*' => Http::response(['status' => 'COMPLETE'], 200, ['Content-Type: application/json']),
            HubspotEmailChannel::HUBSPOT_URL_V3.'contacts/*' => Http::response(
                ['id' => '987654321', 'properties' => ['associatedcompanyid' => '9876']],
                200,
                ['Content-Type: application/json']
            ),
            '*' => Http::response(['id' => '18339394130'], 201, ['Content-Type: application/json']),
        ]);

        $channelResponse = $this->channel->send(new TestNotifiable, new TestLineMailNotification);

        $this->assertSame('18339394130', $channelResponse['id']);
    }

    #[Test]
    public function it_not_send_a_notification_to_notifiable_without_contact_id()
    {
        $this->mockHubspotResponse();

        $channelResponse = $this->channel->send(new TestNotifiableWithoutContactId, new TestLineMailNotification);
        $this->assertNull($channelResponse);
    }

    #[Test]
    public function it_can_send_a_notification_with_line_email()
    {
        $this->mockHubspotResponse();

        $channelResponse = $this->channel->send(new TestNotifiable, new TestLineMailNotification);

        $this->assertIsArray($channelResponse);
        $this->assertEquals($channelResponse['archived'], false);
        $this->assertEquals($channelResponse['properties']['hubspot_owner_id'], config('hubspot.hubspot_owner_id'));
        $this->assertArrayHasKey('id', $channelResponse);
        $this->assertArrayHasKey('hs_email_status', $channelResponse['properties']);
        $htmlString = $channelResponse['properties']['hs_email_text'];
        $this->assertStringContainsString('Greeting', $htmlString);
        $this->assertStringContainsString('Line', $htmlString);
        $this->assertStringContainsString('button', $htmlString);
        $this->assertStringContainsString('https://www.google.it', $htmlString);
        $this->assertEquals($channelResponse['properties']['hs_email_subject'], 'Subject');
    }

    #[Test]
    public function it_can_send_a_notification_with_view_email()
    {
        $this->mockHubspotResponse();

        $channelResponse = $this->channel->send(new TestNotifiable, new TestViewMailNotification);

        $this->assertIsArray($channelResponse);
        $this->assertIsString($channelResponse['properties']['hs_email_text']);
        $this->assertEquals($channelResponse['properties']['hs_email_subject'], 'Subject');
        $this->assertStringContainsString('Test View Content', $channelResponse['properties']['hs_email_text']);
    }

    #[Test]
    public function it_can_send_a_notification_with_markdown_email()
    {
        $this->mockHubspotResponse();
        $channelResponse = $this->channel->send(new TestNotifiable, new TestMarkdownMailNotification);

        $this->assertIsArray($channelResponse);
        $htmlString = $channelResponse['properties']['hs_email_text'];
        $this->assertStringContainsString('Markdown Title Content', $htmlString);
        $this->assertStringContainsString('Markdown body content', $htmlString);
    }

    #[Test]
    public function it_associates_the_email_to_the_contact_with_a_hubspot_defined_association_spec()
    {
        $this->mockHubspotResponse();

        $this->channel->send(new TestNotifiable, new TestLineMailNotification);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && strpos($request->url(), HubspotEmailChannel::HUBSPOT_URL_V4.'contact/987654321/associations/email/18339394130') === 0
                && $request->data() === [
                    [
                        'associationCategory' => 'HUBSPOT_DEFINED',
                        'associationTypeId' => HubspotEmailChannel::ASSOCIATION_CONTACT_TO_EMAIL,
                    ],
                ];
        });
    }

    #[Test]
    public function it_associates_the_email_to_the_company_when_enabled()
    {
        $this->mockHubspotResponse();
        $this->app['config']->set('hubspot.company_email_associations', true);

        $this->channel->send(new TestNotifiable, new TestLineMailNotification);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && strpos($request->url(), HubspotEmailChannel::HUBSPOT_URL_V4.'company/9876/associations/email/18339394130') === 0
                && $request->data() === [
                    [
                        'associationCategory' => 'HUBSPOT_DEFINED',
                        'associationTypeId' => HubspotEmailChannel::ASSOCIATION_COMPANY_TO_EMAIL,
                    ],
                ];
        });
    }

    #[Test]
    public function it_can_send_a_notification_with_to_hubspot_text_mail_method()
    {
        $this->mockHubspotResponse();
        $channelResponse = $this->channel->send(new TestNotifiable, new TestToHubspotTextMailMethodNotification);

        $this->assertIsArray($channelResponse);
        $htmlString = $channelResponse['properties']['hs_email_text'];
        $this->assertStringContainsString('test message', $htmlString);
    }
}
