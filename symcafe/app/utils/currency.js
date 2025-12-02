// Format currency in Indonesian Rupiah format
export const formatCurrency = (amount) => {
  if (typeof amount !== 'number') {
    amount = parseFloat(amount) || 0;
  }
  return `Rp ${amount.toLocaleString('id-ID')}`;
};

