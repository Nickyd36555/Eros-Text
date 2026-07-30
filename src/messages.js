// SMS copy for each order event.
//
// IMPORTANT: Every message is branded ONLY as the store (Eros Labs). It must
// never describe or hint at the product category. Keep the language generic:
// "order", "package", "on its way" — nothing product-specific.

function build(storeName, body) {
  // Prefix every text with the brand so customers recognize the sender.
  return `${storeName}: ${body}`;
}

export function buildMessage(event, { storeName, firstName, orderNumber, tracking }) {
  const hi = firstName ? `${firstName}, ` : '';
  const num = orderNumber ? ` #${orderNumber}` : '';

  switch (event) {
    case 'placed':
      return build(
        storeName,
        `${hi}thanks for your order${num}! We've got it and will text you when it's on the way. Reply STOP to opt out.`,
      );

    case 'processing':
      return build(
        storeName,
        `good news${firstName ? `, ${firstName}` : ''}! Your order${num} is being processed and prepped for shipment.`,
      );

    case 'shipped': {
      if (tracking?.number) {
        const carrier = tracking.provider ? `${tracking.provider} ` : '';
        const link = tracking.url ? ` Track it: ${tracking.url}` : '';
        return build(
          storeName,
          `your order${num} has shipped! ${carrier}tracking: ${tracking.number}.${link}`.replace(/\s+/g, ' ').trim(),
        );
      }
      return build(storeName, `your order${num} has shipped and is on its way!`);
    }

    default:
      return '';
  }
}
