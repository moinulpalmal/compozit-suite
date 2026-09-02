<?php

namespace App\Enums\Merchandising;

use App\Enums\RecordStatus;
use App\Services\Merchandising\PoParser\StateMachine\SectionStateMachine;

/**
 * The section of a Walmart purchase-order document the parser is currently reading.
 *
 * The document is a fixed-width text report, not a structured format, so sections
 * are recognised by the line that starts them and the parser walks from one to the
 * next. {@see SectionStateMachine}
 * owns the transition table.
 *
 * **This is a parser-internal vocabulary, not a workflow status.** A purchase order
 * moving through its own lifecycle is a different concept — see
 * {@see RecordStatus}, whose docblock reserves that ground.
 */
enum ParserState: string
{
    case DocumentStart = 'document_start';
    case PageHeader = 'page_header';
    case MasterData = 'master_data';
    case AddressBlock = 'address_block';
    case Notes = 'notes';
    case SummaryTable = 'summary_table';
    case Logistics = 'logistics';
    case Factory = 'factory';
    case ShipComments = 'ship_comments';
    case MiscComments = 'misc_comments';
    case Product = 'product';
    case Tariff = 'tariff';
    case PackCost = 'pack_cost';
    case LineItemHeader = 'line_item_header';
    case LineItemRows = 'line_item_rows';
    case PackComments = 'pack_comments';
    case PageFooter = 'page_footer';
}
