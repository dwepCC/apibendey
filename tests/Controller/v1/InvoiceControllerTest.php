<?php
/**
 * Created by PhpStorm.
 * User: Giansalex
 * Date: 18/02/2018
 * Time: 11:57
 */

namespace App\Tests\Controller\v1;

use App\Service\ConfigProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class InvoiceControllerTest extends WebTestCase
{
    private const TEST_RUC = '20480072872';
    private const TEST_CERT_FILE = self::TEST_RUC . '-cert.pem';

    protected function setUp(): void
    {
        parent::setUp();
        $projectDir = dirname(__DIR__, 3);
        $dataPath = $projectDir . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($dataPath)) {
            mkdir($dataPath, 0755, true);
        }
        $certDest = $dataPath . DIRECTORY_SEPARATOR . self::TEST_CERT_FILE;
        $certSrc = __DIR__ . '/../../Resources/cert.pem';
        if (file_exists($certSrc)) {
            copy($certSrc, $certDest);
        }
    }

    public function testSendAccessDenied()
    {
        $this->expectException(AccessDeniedHttpException::class);
        $client = $this->getClientConfigured();

        $client->request(
            'POST',
            '/api/v1/invoice/send');

        $response = $client->getResponse();

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testSend()
    {
        $data = file_get_contents(__DIR__.'/../../Resources/documents/invoice.json');

        $client = $this->getClientConfigured();

        $client->request(
            'POST',
            '/api/v1/invoice/send?token=123456',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data);

        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $result = json_decode($response->getContent());
        $this->assertNotEmpty($result->xml);
        $this->assertNotEmpty($result->hash);
        $this->assertNotNull($result->sunatResponse);
        $this->assertTrue($result->sunatResponse->success);
        $this->assertEquals('0', $result->sunatResponse->cdrResponse->code);
        $this->assertCount(0, $result->sunatResponse->cdrResponse->notes);
    }

    public function testXml()
    {
        $data = file_get_contents(__DIR__.'/../../Resources/documents/invoice.json');

        $client = $this->getClientConfigured();

        $client->request(
            'POST',
            '/api/v1/invoice/xml?token=123456',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $data);

        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $result = $response->getContent();
        $doc = new \DOMDocument();
        $doc->loadXML($result);
        $this->assertEquals('Invoice', $doc->documentElement->nodeName);
    }

    /**
     * @return ConfigProviderInterface
     */
    private function getFileConfig()
    {
        $stub = $this->getMockBuilder(ConfigProviderInterface::class)
                    ->getMock();

        $companies = [
            self::TEST_RUC => [
                'SOL_USER' => self::TEST_RUC . 'MODDATOS',
                'SOL_PASS' => 'datos',
                'certificate' => self::TEST_CERT_FILE,
                'logo' => null,
                'ambiente' => 'pruebas',
            ],
        ];

        $stub->method('get')
            ->willReturnCallback(function ($key) use ($companies) {
                if ($key === 'companies') {
                    return json_encode($companies);
                }
                if ($key === 'certificate') {
                    $path = __DIR__ . '/../../Resources/cert.pem';
                    return file_get_contents($path);
                }
                return '';
            });

        /** @var ConfigProviderInterface $stub */
        return $stub;
    }

    private function getClientConfigured()
    {
        $client = static::createClient();
        $client->getContainer()->set(ConfigProviderInterface::class, $this->getFileConfig());
        return $client;
    }
}
