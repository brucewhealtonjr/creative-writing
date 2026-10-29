# Person identity

The Full name, Born and Died fields generate the node title. Drupal's standard
entity-reference autocomplete searches that title, so namesakes appear with life
dates. Separate optional name components supply structured data. Approximate and
year-only dates remain text and never become invented full dates in JSON-LD.

Pathauto generates `/person/Name_With_Underscores_(birth-death)` while its normal
uniquifier handles collisions. Custom manual aliases remain possible. Titles and
paths use the supplied date precision. A missing death date does not itself mean
living; Life status records that separately.

Mother, Father, Siblings and Children allow selecting existing Person nodes or
creating a new linked Person on save. New labels such as `Jane Doe (1800-1870)`
populate the name and date fields. Relationships are explicitly entered; this
module does not infer siblings, create reciprocal links, or invent relatives.

One JSON-LD graph describes the ProfilePage and Person, with a stable UUID URN,
profile sameAs URLs and accessible family links. Related links are displayed by
Drupal but are not automatically sameAs. Existing Schema Person metatags are
suppressed on Person pages to avoid conflicting duplicate Person objects.

Before enabling, back up the database and configuration. Enable with Drush, then
export the complete current live configuration rather than importing a stale
local export. The install hook creates fields and updates Person displays only;
it does not alter existing biography content.

Book Cover and Description and External Published Books have separate unlimited
Author(s) and Subject(s) references to Person records. Selected authors become
Book.author objects with stable Person IDs, names and biography URLs. Selected
subjects become Book.about; those book records also appear in Person.subjectOf.
No subject relationship is inferred merely from authorship. Other authored works
are not automatically listed on a Person page.

The route subscriber replaces Gutenberg's internal link-search controller while
retaining its existing permission requirement. Results use canonical Drupal URLs
including the installation base path and readable content-type labels. Published
content and per-node view access are checked; responses are private/no-store.
