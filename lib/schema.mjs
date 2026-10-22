// Gripp API v3 — schema-samenvatting (bron: https://api.gripp.com/public/api3.php, 15-10-2026).
// Alleen wat importer en viewer nodig hebben: welke entiteiten we lezen, en welke velden naar
// welke andere entiteit verwijzen. Wordt gedeeld door importer (Node) en viewer (browser).

// Entiteiten die geïmporteerd worden (alleen .get). Volgorde = importvolgorde.
// 'order: false' = entiteit heeft geen id-veld om op te sorteren.
export const ENTITIES = [
  // referentiedata
  { name: 'tag' }, { name: 'unit' }, { name: 'department' }, { name: 'costheading' },
  { name: 'ledger' }, { name: 'offerphase' }, { name: 'projectphase' }, { name: 'taskphase' },
  { name: 'tasktype' }, { name: 'rejectionreason' }, { name: 'yeartargettype' }, { name: 'yeartarget' },
  // CRM
  { name: 'company' }, { name: 'contact' }, { name: 'companydossier' }, { name: 'revenuetarget' },
  { name: 'priceexception' },
  // medewerkers
  { name: 'employee' }, { name: 'employmentcontract' }, { name: 'employeefamily' },
  { name: 'employeetarget' },
  { name: 'absencerequest' }, { name: 'absencerequestline' },
  // producten
  { name: 'product' }, { name: 'bulkprice' }, { name: 'packet' }, { name: 'packetline' },
  // verkoop / projecten
  { name: 'umbrellaproject' }, { name: 'offer' }, { name: 'project' }, { name: 'offerprojectline' },
  { name: 'contract' }, { name: 'contractline' },
  // inkoop
  { name: 'purchaseorder' }, { name: 'purchaseorderline' },
  { name: 'purchaseinvoice' }, { name: 'purchaseinvoiceline' }, { name: 'purchasepayment' },
  // facturatie
  { name: 'invoice' }, { name: 'invoiceline' }, { name: 'payment' },
  // planning / uren
  { name: 'task' }, { name: 'calendaritem' }, { name: 'hour' },
  // boekhouding
  { name: 'memorial' }, { name: 'memorialline' }, { name: 'cost' },
  // overig
  { name: 'timelineentry' }, { name: 'file' }, { name: 'externallink', order: false },
];

// Verwijzingen: veld -> doel-entiteit. '@offerproject' = offer óf project (zelfde id-ruimte in Gripp).
const R = (o) => o;
export const REFS = {
  absencerequest: R({ employee: 'employee' }),
  absencerequestline: R({ absencerequest: 'absencerequest' }),
  bulkprice: R({ product: 'product' }),
  calendaritem: R({ calendaritememployee: 'employee', timelineentry: 'timelineentry', task: 'task' }),
  company: R({ accountmanager: 'employee', tags: 'tag', files: 'file' }),
  companydossier: R({ company: 'company', files: 'file' }),
  contact: R({ company: 'company', tags: 'tag' }),
  contract: R({ company: 'company', contact: 'contact', tags: 'tag', employees: 'employee', employees_starred: 'employee', extrapdf1: 'file', extrapdf2: 'file', files: 'file', contractlines: 'contractline' }),
  contractline: R({ contract: 'contract', product: 'product', unit: 'unit' }),
  cost: R({ ledger: 'ledger', costheading: 'costheading' }),
  employee: R({ userphoto: 'file', department: 'department', tags: 'tag', skills: 'tasktype' }),
  employeeyearlyleavebudget: R({ employee: 'employee' }),
  employeefamily: R({ employee: 'employee' }),
  employeetarget: R({ employee: 'employee' }),
  employmentcontract: R({ employee: 'employee' }),
  hour: R({ task: 'task', employee: 'employee', offerprojectbase: '@offerproject', offerprojectline: 'offerprojectline', invoiceline: 'invoiceline', authorizedby: 'employee', definitiveby: 'employee' }),
  invoice: R({ company: 'company', contact: 'contact', tags: 'tag', extrapdf1: 'file', extrapdf2: 'file', files: 'file', invoicelines: 'invoiceline', payments: 'payment' }),
  invoiceline: R({ invoice: 'invoice', ledger: 'ledger', costheading: 'costheading', product: 'product', unit: 'unit', project: 'project', umbrellaproject: 'umbrellaproject', part: 'offerprojectline', contractline: 'contractline', contract: 'contract' }),
  memorial: R({ files: 'file', tags: 'tag', memoriallines: 'memorialline' }),
  memorialline: R({ ledger: 'ledger', costheading: 'costheading', memorial: 'memorial' }),
  offer: R({ phase: 'offerphase', company: 'company', contact: 'contact', accountmanager: 'employee', salesassociate: 'employee', extrapdf1: 'file', extrapdf2: 'file', files: 'file', tags: 'tag', employees: 'employee', employees_starred: 'employee', offerlines: 'offerprojectline', umbrellaproject: 'umbrellaproject' }),
  offerprojectline: R({ unit: 'unit', offerprojectbase: '@offerproject', contractline: 'contractline', product: 'product' }),
  packet: R({ unit: 'unit', tags: 'tag', packetlines: 'packetline' }),
  packetline: R({ packet: 'packet', product: 'product', unit: 'unit' }),
  payment: R({ invoice: 'invoice' }),
  priceexception: R({ company: 'company', product: 'product' }),
  product: R({ unit: 'unit', tasktype: 'tasktype', costheading: 'costheading', ledger: 'ledger', purchaseledger: 'ledger', supplier: 'company', tags: 'tag', attachments_internal: 'file', attachments_external: 'file' }),
  project: R({ accountmanager: 'employee', phase: 'projectphase', company: 'company', contact: 'contact', tags: 'tag', employees: 'employee', employees_starred: 'employee', extrapdf1: 'file', extrapdf2: 'file', files: 'file', projectlines: 'offerprojectline', umbrellaproject: 'umbrellaproject' }),
  purchaseinvoice: R({ company: 'company', contact: 'contact', tags: 'tag', employees: 'employee', employees_starred: 'employee', files: 'file', purchaseinvoicelines: 'purchaseinvoiceline', payments: 'purchasepayment' }),
  purchaseinvoiceline: R({ purchaseinvoice: 'purchaseinvoice', costheading: 'costheading', ledger: 'ledger', purchaseorderline: 'purchaseorderline', project: '@offerproject', part: 'offerprojectline' }),
  purchaseorder: R({ company: 'company', contact: 'contact', tags: 'tag', employees: 'employee', employees_starred: 'employee', supplier: 'company', extrapdf1: 'file', extrapdf2: 'file', files: 'file', accountmanager: 'employee', purchaseorderlines: 'purchaseorderline' }),
  purchaseorderline: R({ costheading: 'costheading', ledger: 'ledger', purchaseorder: 'purchaseorder', projectline: 'offerprojectline', project: '@offerproject' }),
  purchasepayment: R({ purchaseinvoice: 'purchaseinvoice' }),
  revenuetarget: R({ company: 'company' }),
  task: R({ type: 'tasktype', phase: 'taskphase', company: 'company', offerprojectbase: '@offerproject', offerprojectline: 'offerprojectline', preferredemployee: 'employee', packet: 'packet', files: 'file', calendaritems: 'calendaritem' }),
  timelineentry: R({ employee: 'employee', company: 'company', contact: 'contact', offer: 'offer', project: 'project', invoice: 'invoice', purchaseinvoice: 'purchaseinvoice', purchaseorder: 'purchaseorder', contract: 'contract', task: 'task', files: 'file', calendaritem: 'calendaritem' }),
  umbrellaproject: R({ tags: 'tag' }),
  yeartarget: R({ yeartargettype: 'yeartargettype' }),
};

// Velden die (mogelijk) file-id's bevatten → bijlagen ophalen via file.getContent.
export const FILE_FIELDS = ['files', 'extrapdf1', 'extrapdf2', 'attachments_internal', 'attachments_external', 'userphoto'];

// Toegestane API-methoden. Alles anders wordt geweigerd (view-only garantie).
export const ALLOWED_METHOD = /^[a-z]+\.get$|^file\.getContent$/;
