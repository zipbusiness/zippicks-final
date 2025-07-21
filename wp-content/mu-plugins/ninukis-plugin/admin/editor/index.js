import { registerPlugin } from '@wordpress/plugins';
import PurgeCacheSummaryPanel from './components/PurgeCacheSummaryPanel';

registerPlugin(
  'pressidium-purge-cache-summary-panel',
  { render: PurgeCacheSummaryPanel }
);
