<?php
namespace App\Enums;
enum AssetStatus: string
{
    case DRAFT = 'draft';
    case REGISTERED = 'registered';
    case MATCHING = 'matching';
    case REQUESTED = 'requested';
    case PARTNER_ACCEPTED = 'partner_accepted';
    case OFFERED = 'offered';
    case SCHEDULED = 'scheduled';
    case COLLECTED = 'collected';
    case RECEIVED = 'received';
    case IN_PROCESSING = 'in_processing';
    case ASSESSMENT = 'assessment';
    case NEEDS_TRANSFER = 'needs_transfer';
    case TRANSFER_PENDING = 'transfer_pending';
    case TRANSFERRED = 'transferred';
    case REUSED = 'reused';
    case REPAIRED = 'repaired';
    case DONATED = 'donated';
    case PARTS_RECOVERED = 'parts_recovered';
    case RECEIVED_BY_RECOVERY = 'received_by_recovery';
    case RECOVERY_CONFIRMED = 'recovery_confirmed';
    case SPECIAL_HANDLING_COMPLETED = 'special_handling_completed';
    case RETURNED = 'returned';
    case UNVERIFIED = 'unverified';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}
