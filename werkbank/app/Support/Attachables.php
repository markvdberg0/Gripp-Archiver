<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Models\TimelineEntry;

/**
 * Waar bestanden aan kunnen hangen: morph-alias => [model, recht om te bekijken, recht om te uploaden, routenaam].
 * Wordt per fase aangevuld.
 */
final class Attachables
{
    public const MAP = [
        'company' => [Company::class, 'relaties.bekijken', 'relaties.bewerken', 'relaties.show'],
        'contact' => [Contact::class, 'relaties.bekijken', 'relaties.bewerken', 'contactpersonen.show'],
        'timeline' => [TimelineEntry::class, 'relaties.bekijken', 'relaties.bewerken', null],
        'offer' => [Offer::class, 'offertes.bekijken', 'offertes.bewerken', 'offertes.show'],
        'project' => [Project::class, 'opdrachten.bekijken', 'opdrachten.bewerken', 'opdrachten.show'],
        'task' => [Task::class, 'opdrachten.bekijken', 'taken.bewerken', 'taken.show'],
        'product' => [Product::class, 'producten.bekijken', 'producten.bewerken', 'producten.show'],
        'invoice' => [Invoice::class, 'facturen.bekijken', 'facturen.bewerken', 'facturen.show'],
        'contract' => [Contract::class, 'facturen.bekijken', 'facturen.bewerken', 'contracten.show'],
        'purchase_order' => [PurchaseOrder::class, 'inkoop.bekijken', 'inkoop.bewerken', 'inkoopopdrachten.show'],
        'purchase_invoice' => [PurchaseInvoice::class, 'inkoop.bekijken', 'inkoop.bewerken', 'inkoopfacturen.show'],
    ];

    /** Morph-map voor Relation::enforceMorphMap (alleen modellen die bestaan). */
    public static function morphMap(): array
    {
        $map = [];
        foreach (self::MAP as $alias => [$class]) {
            if (class_exists($class)) {
                $map[$alias] = $class;
            }
        }

        return $map;
    }

    public static function aliasFor(string $class): ?string
    {
        foreach (self::MAP as $alias => [$c]) {
            if ($c === $class) {
                return $alias;
            }
        }

        return null;
    }
}
