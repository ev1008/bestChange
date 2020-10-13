<?php

namespace BestChange;

use BestChange\Exception\ECurrencyNotReceived;
use DiDom\Document;

/**
 * Коды взяты отсюда: https://www.bestchange.ru/wiki/rates.html
 * нет API для получения кода валют
 */
class ECurrencyCodes
{
    const PAGEURL = 'https://www.bestchange.ru/wiki/rates.html';
    const FILENAME = '/e-currency-codes';
    const TIMEOUT = 20;
    private $pathfile;
    private $data;
    private $currencies;

    /**
     * ECurrencyCodes constructor.
     * @param Currencies $currencies
     * @throws ECurrencyNotReceived
     */
    public function __construct(Currencies $currencies)
    {
        $this->currencies = $currencies;
        $this->pathfile = __DIR__ . self::FILENAME;
        if (file_exists($this->pathfile)) {
            $this->data = json_decode(file_get_contents($this->pathfile), true);
            return;
        }
        $this->refreshCurrenciesCodes();
    }

    /**
     * @param $id
     * @return mixed
     * @throws ECurrencyNotReceived
     */
    public function getByID($id)
    {
        if (empty($this->data[$id])) {
            $this->refreshCurrenciesCodes();
        }
        return $this->data[$id];
    }

    public function get()
    {
        return $this->data;
    }

    /**
     * @throws ECurrencyNotReceived
     */
    public function refreshCurrenciesCodes()
    {
        $codes = $this->getCodes();
        $currencies = $this->currencies->get();
        foreach ($currencies as $currency) {
            if (empty($codes[$currency['name']])) {
                throw new ECurrencyNotReceived('Несоответствие данных таблицы «Коды электронных валют» и bm_cy.dat');
            }
            $this->data[$currency['id']] = [
                'code' => $codes[$currency['name']],
            ];
        }
        file_put_contents($this->pathfile, json_encode($this->data, JSON_UNESCAPED_UNICODE));
        return $this;
    }

    /**
     * @throws ECurrencyNotReceived
     */
    private function getCodes()
    {
        $page = $this->getPage();
        $doc = new Document($page);
        if (!$doc->has('h2')) {
            throw new ECurrencyNotReceived('Коды электронных валют не получены. Проверьте доступность ' . self::PAGEURL);
        }
        foreach ($doc->find('h2') as $title) {
            if (preg_match('/Коды\s+электронных\s+валют/sui', $title->text())) {
                $table = $title->nextSibling('table.codetable');
                break;
            }
        }
        if (empty($table)) {
            throw new ECurrencyNotReceived('Коды электронных валют не получены. Проверьте наличие таблицы с кодами на странице ' . self::PAGEURL);
        }
        $codes = [];
        foreach ($table->find('tr') as $row) {
            if (!$row->has('i')) {
                continue;
            }
            $cols = $row->find('td');
            if (count($cols) != 2) {
                throw new ECurrencyNotReceived('Ошибка парсинга таблицы «Коды электронных валют». Таблица с кодами должна содержать 2 колонки (' . self::PAGEURL . ')');
            }
            $codes[trim($cols[1]->first('i')->text())] = trim($cols[0]->text());
        }
        return $codes;
    }

    private function getPage()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://www.bestchange.ru/wiki/rates.html",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $data = curl_exec($curl);

        curl_close($curl);
        return iconv('CP1251', 'UTF-8', $data);
    }
}
