<?php

namespace Datomatic\LaravelHubspotEmailNotificationChannel\Test;

use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\CouldNotSendNotification;
use Datomatic\LaravelHubspotEmailNotificationChannel\Exceptions\InvalidConfiguration;
use Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Orchestra\Testbench\TestCase;

class ChannelFeatureTest extends TestCase
{
    /** @var \Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailChannel */
    protected $channel;

    protected function getPackageProviders($app)
    {
        return ['Datomatic\LaravelHubspotEmailNotificationChannel\HubspotEmailServiceProvider'];
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->channel = new HubspotEmailChannel();
    }

    public function tearDown(): void
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
            if (strpos($request->url(), 'associations/default/email') !== false
                && strpos($request->url(), HubspotEmailChannel::HUBSPOT_URL_V4) !== false
            ) {
                $path = trim($request->url(), HubspotEmailChannel::HUBSPOT_URL_V4 . 'contact/');
                list($hubspotContactId,$hubspotEmailId) = explode('/associations/default/email/', $path, 2);

                return Http::response(json_encode([
                    "status" => "COMPLETE",
                    "results" => [
                        [
                            "from" => [
                                "id" => $hubspotEmailId,
                            ],
                            "to" => [
                                "id" => $hubspotContactId,
                            ],
                            "associationSpec" => [
                                "associationCategory" => "HUBSPOT_DEFINED",
                                "associationTypeId" => 198,
                            ],
                        ],
                        [
                            "from" => [
                                "id" => $hubspotContactId,
                            ],
                            "to" => [
                                "id" => $hubspotEmailId,
                            ],
                            "associationSpec" => [
                                "associationCategory" => "HUBSPOT_DEFINED",
                                "associationTypeId" => 197,
                            ],
                        ],
                    ],
                    "startedAt" => round(microtime(true) * 1000),
                    "completedAt" => round(microtime(true) * 1000),
                ]), 200, ['Content-Type: application/json']);
            } elseif (strpos($request->url(), 'emails') !== false
                && strpos($request->url(), HubspotEmailChannel::HUBSPOT_URL_V3) !== false
            ) {
                $data = $request->data()['properties'];

                return Http::response('{
    "id": "18339394130",
    "properties": {
        "hs_all_owner_ids": "' . $data['hubspot_owner_id'] . '",
        "hs_body_preview": "Thanks for your interest let\'s find a time to connect",
        "hs_body_preview_html": "Thanks for your interest let\'s find a time to connect",
        "hs_body_preview_is_truncated": "false",
        "hs_createdate": "' . round(microtime(true) * 1000) . '",
        "hs_email_attached_video_opened": "false",
        "hs_email_attached_video_watched": "false",
        "hs_email_direction": "EMAIL",
        "hs_email_status": "SENT",
        "hs_email_subject": "' . str_replace('"', '\"', $data['hs_email_subject']) . '",
        "hs_email_text": "' . preg_replace("/\r|\n/", "", str_replace('"', '\"', $data['hs_email_text'])) . '",
        "hs_lastmodifieddate": "' . round(microtime(true) * 1000) . '",
        "hs_object_id": "18339394130",
        "hs_timestamp": "' . $data['hs_timestamp'] . '",
        "hubspot_owner_assigneddate": "' . round(microtime(true) * 1000) . '",
        "hubspot_owner_id": "' . $data['hubspot_owner_id'] . '"
    },
    "createdAt": "' . round(microtime(true) * 1000) . '",
    "updatedAt": "' . round(microtime(true) * 1000) . '",
    "archived": false}', 201, ['Content-Type: application/json']);
            }else{
                return Http::response('{}',200, ['Content-Type: application/json']);
            }
        });
    }

    private function mockHubspotErrorRequest()
    {
        $this->configSetUp();
        Http::fake(['*' => Http::response('Error', 404)]);
    }

    /** @test */
    public function it_throws_an_exception_when_it_is_not_configured()
    {
        Config::set('hubspot', null);
        $this->expectException(InvalidConfiguration::class);

        (new TestNotifiable())->notify(new TestLineMailNotification());
    }

    /** @test */
    public function it_throws_an_exception_when_it_could_not_send_the_notification()
    {
        $this->mockHubspotErrorRequest();
        $this->expectException(CouldNotSendNotification::class);

        $this->channel->send(new TestNotifiable(), new TestLineMailNotification());
    }

    /** @test */
    public function it_not_send_a_notification_to_notifiable_without_contact_id()
    {
        $this->mockHubspotResponse();

        $channelResponse = $this->channel->send(new TestNotifiableWithoutContactId(), new TestLineMailNotification());
        $this->assertNull($channelResponse);
    }

    /** @test */
    public function it_can_send_a_notification_with_line_email()
    {
        $this->mockHubspotResponse();

        $channelResponse = $this->channel->send(new TestNotifiable(), new TestLineMailNotification());

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

    /** @test */
    public function it_can_send_a_notification_with_view_email()
    {
        $this->mockHubspotResponse();

        $channelResponse = $this->channel->send(new TestNotifiable(), new TestViewMailNotification());

        $this->assertIsArray($channelResponse);
        $this->assertIsString($channelResponse['properties']['hs_email_text']);
        $this->assertEquals($channelResponse['properties']['hs_email_subject'], 'Subject');
        $this->assertStringContainsString('Test View Content', $channelResponse['properties']['hs_email_text']);
    }

    /** @test */
    public function it_can_send_a_notification_with_markdown_email()
    {
        $this->mockHubspotResponse();
        $channelResponse = $this->channel->send(new TestNotifiable(), new TestMarkdownMailNotification());

        $this->assertIsArray($channelResponse);
        $htmlString = $channelResponse['properties']['hs_email_text'];
        $this->assertStringContainsString('Markdown Title Content', $htmlString);
        $this->assertStringContainsString('Markdown body content', $htmlString);
    }
}
