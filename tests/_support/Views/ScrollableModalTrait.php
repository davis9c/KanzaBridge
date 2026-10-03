<?php

namespace Tests\Support\Views;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Pemeriksaan markup untuk modal yang memakai `modal-dialog-scrollable`.
 *
 * Kenapa ini perlu dijaga: `modal-dialog-scrollable` hanya bekerja kalau
 * `.modal-body` adalah anak LANGSUNG dari `.modal-content`.
 *
 * Aturan Bootstrap 5.3 yang relevan:
 *
 *   .modal-dialog-scrollable             { height: calc(100% - margin*2) }
 *   .modal-dialog-scrollable .modal-content { max-height: 100%; overflow: hidden }
 *   .modal-dialog-scrollable .modal-body { overflow-y: auto }   <- yang harus scroll
 *
 * Rantai flex itu harus utuh dari `.modal-content` ke `.modal-body`. Kalau ada
 * `<form>` di antaranya — pola yang sangat wajar untuk modal ber-form — maka
 * `<form>` menjadi flex item dengan `height: auto` dan `min-height: auto`, dan:
 *
 *   1. `.modal-body` tumbuh mengikuti isi, jadi `overflow-y: auto` tidak pernah
 *      berefek dan tidak ada yang bisa di-scroll;
 *   2. `.modal-content` yang punya `max-height: 100%; overflow: hidden` memotong
 *      bagian bawah, termasuk footer dan tombol submit;
 *   3. `.modal` sendiri tidak punya overflow, karena tinggi `.modal-dialog`
 *      (100% - margin*2) + margin-nya (margin*2) = persis 100%.
 *
 * Hasilnya modal terpotong tanpa scrollbar di mana pun, dan tidak ada error
 * JavaScript sama sekali — gejalanya murni visual, sehingga assertion markup
 * biasa ("modal ini ada") tidak pernah menangkapnya.
 *
 * PHPUnit tidak bisa mengukur scroll, jadi yang dikunci di sini adalah
 * prasyaratnya lewat struktur DOM.
 */
trait ScrollableModalTrait
{
    /**
     * @param string $html HTML halaman utuh, hasil render view.
     */
    protected function assertScrollableModalBodyIsDirectChild(string $html): void
    {
        $dom      = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        // Fragment HTML dari view bisa tidak punya <html>/<body>, jadi dibungkus.
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>',
            LIBXML_NOWARNING | LIBXML_NOERROR
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath   = new DOMXPath($dom);
        $dialogs = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' modal-dialog-scrollable ')]"
        );

        $this->assertNotFalse($dialogs, 'XPath gagal mencari modal scrollable.');
        $this->assertGreaterThan(
            0,
            $dialogs->length,
            'Prasyarat: halaman ini harus punya minimal satu modal scrollable.'
        );

        $total = $dialogs->length;

        for ($i = 0; $i < $total; $i++) {
            $dialog = $dialogs->item($i);

            $this->assertInstanceOf(DOMElement::class, $dialog);

            $content = $xpath->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' modal-content ')]",
                $dialog
            )->item(0);

            $this->assertInstanceOf(
                DOMElement::class,
                $content,
                'Modal scrollable #' . ($i + 1) . ' tidak punya .modal-content.'
            );

            /** @var DOMElement $content */
            $directBody = $xpath->query(
                "./*[contains(concat(' ', normalize-space(@class), ' '), ' modal-body ')]",
                $content
            );

            $this->assertGreaterThan(
                0,
                $directBody->length,
                'Modal scrollable #' . ($i + 1) . ' / ' . $total . ': .modal-body bukan anak langsung dari '
                . '.modal-content. Kalau ada <form> di antaranya, rantai flex terputus — .modal-body tidak '
                . 'pernah jadi scroll container, .modal-content yang memotong (overflow: hidden), dan tidak '
                . 'ada scrollbar. Perbaiki dengan menjadikan <form> itu .modal-content, bukan membungkusnya '
                . 'di dalam .modal-content.'
            );
        }
    }
}