'use strict';

// Step definitions for the Web Patches admin report.
//
// The report is a stack of <details> sections, each holding one or more
// tables whose cells carry links that are derived from Composer data. The
// steps below name a table the way a human reads the page — by the heading
// or the section title above it — so the feature files never carry a CSS
// path, and they assert the RULE behind each link (a drupal/* package links
// to its drupal.org project, a "#NNNNNNN" reference links to that node, a
// "--mr-<id>" patch file links to that merge request) instead of pinning the
// exact URLs that happen to be declared today.

const { Then } = require('@cucumber/cucumber');
const assert = require('assert');

/**
 * Reads a named table off the report page into a plain object.
 *
 * A table is addressed either by the <h3> heading directly above it
 * ("Declaration files", "Packages declaring patches") or by the title of the
 * <details> section that wraps it ("Patches", "Ignored patches"), whose
 * summary also carries a count in brackets.
 *
 * @param {import('playwright').Page} page
 *   The page to read.
 * @param {string} name
 *   The heading or section title of the table.
 *
 * @return {Promise<object>}
 *   The table model: title, open state, headers and rows of cells, each cell
 *   carrying its text and its links.
 */
async function readTable(page, name) {
  const model = await page.evaluate((tableName) => {
    // The site decorates outbound links with an "(link is external)" marker
    // at runtime. It is a rendering of the target attribute, not part of what
    // the report wrote, so it is stripped before anything is compared.
    const textOf = (node) => {
      const copy = node.cloneNode(true);
      copy.querySelectorAll('span.ext, span.mailto, span.extlink').forEach((mark) => mark.remove());
      return (copy.textContent || '')
        .replace(/\s+/g, ' ')
        .replace(/\s*\((?:link is external|link sends email)\)/g, '')
        .trim();
    };

    const linksIn = (node) => Array.from(node.querySelectorAll('a')).map((a) => ({
      text: textOf(a),
      href: a.getAttribute('href'),
    }));

    // A table under an <h3> heading: the first table that follows it.
    const headingTable = () => {
      const heading = Array.from(document.querySelectorAll('h3'))
        .find((h) => textOf(h) === tableName);
      if (!heading) {
        return null;
      }
      const tables = Array.from(document.querySelectorAll('table'));
      const after = tables.find((table) =>
        heading.compareDocumentPosition(table) & Node.DOCUMENT_POSITION_FOLLOWING);
      return after ? { table: after, details: heading.closest('details') } : null;
    };

    // A table inside a <details> whose summary is "<name> (<count>)".
    const sectionTable = () => {
      const details = Array.from(document.querySelectorAll('details')).find((element) => {
        const summary = element.querySelector('summary');
        return summary && textOf(summary).startsWith(`${tableName} (`);
      });
      if (!details) {
        return null;
      }
      const table = details.querySelector('table');
      return table ? { table, details } : null;
    };

    const found = headingTable() || sectionTable();
    if (!found) {
      return null;
    }

    const summary = found.details ? found.details.querySelector('summary') : null;
    // Drupal renders the #empty message of a table as a single full width
    // cell in the body. It is the table saying it has nothing, not a row of
    // data, so it is reported separately and never counted as a row.
    const bodyRows = Array.from(found.table.querySelectorAll('tbody tr'));
    const emptyCell = found.table.querySelector('tbody td.empty.message');
    const dataRows = emptyCell ? [] : bodyRows;

    return {
      title: summary ? textOf(summary) : null,
      open: found.details ? found.details.hasAttribute('open') : null,
      headers: Array.from(found.table.querySelectorAll('thead th')).map(textOf),
      emptyMessage: emptyCell ? textOf(emptyCell) : null,
      rows: dataRows.map((tr) =>
        Array.from(tr.querySelectorAll('td')).map((td) => ({
          text: textOf(td),
          links: linksIn(td),
        }))),
    };
  }, name);

  assert.ok(model, `No table named "${name}" was found on the page.`);
  return model;
}

/**
 * Reads a <details> section of the report by its title.
 *
 * @param {import('playwright').Page} page
 *   The page to read.
 * @param {string} title
 *   The section title, without any bracketed count.
 *
 * @return {Promise<object>}
 *   The section model: its full summary text and whether it is open.
 */
async function readSection(page, title) {
  const model = await page.evaluate((sectionTitle) => {
    const textOf = (node) => (node.textContent || '')
      .replace(/\s+/g, ' ')
      .replace(/\s*\((?:link is external|link sends email)\)/g, '')
      .trim();
    const details = Array.from(document.querySelectorAll('details')).find((element) => {
      const summary = element.querySelector('summary');
      if (!summary) {
        return false;
      }
      const text = textOf(summary);
      return text === sectionTitle || text.startsWith(`${sectionTitle} (`);
    });
    if (!details) {
      return null;
    }
    return {
      summary: textOf(details.querySelector('summary')),
      open: details.hasAttribute('open'),
      tables: details.querySelectorAll('table').length,
    };
  }, title);

  assert.ok(model, `No section titled "${title}" was found on the page.`);
  return model;
}

/**
 * Returns every link in a table, tagged with the row it sits in.
 *
 * @param {object} table
 *   A table model as returned by readTable().
 *
 * @return {object[]}
 *   The links, each with its text, href and owning row.
 */
function allLinks(table) {
  const links = [];
  table.rows.forEach((row, index) => {
    row.forEach((cell) => {
      cell.links.forEach((link) => links.push({ ...link, row, rowIndex: index }));
    });
  });
  return links;
}

/**
 * Returns the drupal.org project machine name of a Composer package.
 *
 * @param {string} pkg
 *   The Composer package name.
 *
 * @return {string|null}
 *   The project name, or null for a package that is not on drupal.org.
 */
function drupalProject(pkg) {
  if (!pkg.startsWith('drupal/')) {
    return null;
  }
  const name = pkg.slice('drupal/'.length);
  return name === 'core' ? 'drupal' : name;
}

/**
 * Returns the index of a column by its header text.
 *
 * @param {object} table
 *   A table model as returned by readTable().
 * @param {string} column
 *   The column header.
 *
 * @return {number}
 *   The zero based column index.
 */
function columnIndex(table, column) {
  const index = table.headers.findIndex((header) => header === column);
  assert.notStrictEqual(
    index,
    -1,
    `The table has no "${column}" column. Columns: ${table.headers.join(', ')}.`
  );
  return index;
}

/**
 * Renders a table model as text, for assertion messages.
 *
 * @param {object} table
 *   A table model as returned by readTable().
 *
 * @return {string}
 *   The table, one row per line.
 */
function render(table) {
  const lines = [table.headers.join(' | ')];
  if (table.emptyMessage !== null) {
    lines.push(`(empty: ${table.emptyMessage})`);
  }
  return lines
    .concat(table.rows.map((row) => row.map((cell) => cell.text).join(' | ')))
    .join('\n');
}

/**
 * Assert the columns of a named table, in order.
 *
 * Example #1: Then the "Declaration files" table should have the columns:
 *               | Source | File | Status |
 * Example #2: Then the "Patches" table should have the columns:
 *               | Package | Patch | Declared in |
 * Example #3: Then the "Ignored patches" table should have the columns:
 *               | Package | Patch | Declared by | Reason |
 * Example #4: Then the "Packages declaring patches" table should have the columns:
 *               | Package | Version | Patches | Status | Reason |
 * Example #5: Then the "Declaration files" table should have the columns:
 *               | Source | File | Status |
 *
 */
Then(/^the "([^"]*)" table should have the columns:$/, async function (name, dataTable) {
  const table = await readTable(this.page, name);
  const expected = dataTable.raw()[0];
  assert.deepStrictEqual(
    table.headers,
    expected,
    `The "${name}" table columns are ${table.headers.join(', ')}, expected ${expected.join(', ')}.`
  );
});

/**
 * Assert that a named table has no column with the given header.
 *
 * Example #1: Then the "Patches" table should not have a "File" column
 * Example #2: Then the "Patches" table should not have a "Source" column
 * Example #3: Then the "Declaration files" table should not have a "Package" column
 * Example #4: Then the "Ignored patches" table should not have a "Version" column
 * Example #5: Then the "Packages declaring patches" table should not have a "Patch" column
 *
 */
Then(/^the "([^"]*)" table should not have an? "([^"]*)" column$/, async function (name, column) {
  const table = await readTable(this.page, name);
  assert.ok(
    !table.headers.includes(column),
    `The "${name}" table should not have a "${column}" column, but its columns are ${table.headers.join(', ')}.`
  );
});

/**
 * Assert the number of body rows of a named table.
 *
 * Example #1: Then the "Declaration files" table should have 3 rows
 * Example #2: Then the "Packages declaring patches" table should have 3 rows
 * Example #3: Then the "Ignored patches" table should have 1 row
 * Example #4: Then the "Patches" table should have 35 rows
 * Example #5: Then the "Declaration files" table should have 3 rows
 *
 */
Then(/^the "([^"]*)" table should have (\d+) rows?$/, async function (name, count) {
  const table = await readTable(this.page, name);
  assert.strictEqual(
    table.rows.length,
    Number(count),
    `The "${name}" table has ${table.rows.length} rows, expected ${count}.\n${render(table)}`
  );
});

/**
 * Assert that a named table has a row whose cells contain the given values.
 *
 * Each data table row lists the expected cell values in column order; an
 * empty cell matches anything, and every other cell matches on substring.
 *
 * Example #1: Then the "Declaration files" table should have the rows:
 *               | Root composer.json | /var/www/html/composer.json | Read |
 * Example #2: Then the "Declaration files" table should have the rows:
 *               | Patches file       | patches.json | Not found |
 *               | Custom patches file | n/a         | Disabled  |
 * Example #3: Then the "Packages declaring patches" table should have the rows:
 *               | webship/patches |  |  | Allowed |  |
 * Example #4: Then the "Ignored patches" table should have the rows:
 *               | All patches | n/a | drupal/webpatches | allowed-dependency-patches |
 * Example #5: Then the "Packages declaring patches" table should have the rows:
 *               | drupal/webpatches |  |  | Not allowed |  |
 *
 */
Then(/^the "([^"]*)" table should have the rows:$/, async function (name, dataTable) {
  const table = await readTable(this.page, name);
  dataTable.raw().forEach((expected) => {
    const match = table.rows.find((row) => expected.every((value, index) => {
      if (value === '') {
        return true;
      }
      return row[index] && row[index].text.includes(value);
    }));
    assert.ok(
      match,
      `The "${name}" table has no row matching | ${expected.join(' | ')} |.\n${render(table)}`
    );
  });
});

/**
 * Assert that a named table nowhere mentions the given text.
 *
 * Example #1: Then the "Declaration files" table should not mention "composer.lock"
 * Example #2: Then the "Declaration files" table should not mention "Installed dependency packages"
 * Example #3: Then the "Patches" table should not mention "Ignored"
 * Example #4: Then the "Ignored patches" table should not mention "composer.lock"
 * Example #5: Then the "Packages declaring patches" table should not mention "composer.lock"
 *
 */
Then(/^the "([^"]*)" table should not mention "([^"]*)"$/, async function (name, text) {
  const table = await readTable(this.page, name);
  const rendered = render(table);
  assert.ok(
    !rendered.includes(text),
    `The "${name}" table should not mention "${text}".\n${rendered}`
  );
});

/**
 * Assert the ordering of two values within a column of a named table.
 *
 * Example #1: Then the "Packages declaring patches" table should list every "Allowed" row in the "Status" column before every "Not allowed" row
 * Example #2: Then the "Packages declaring patches" table should list every "Allowed" row in the "Status" column before every "Not allowed" row
 * Example #3: Then the "Declaration files" table should list every "Read" row in the "Status" column before every "Disabled" row
 * Example #4: Then the "Patches" table should list every "drupal/core" row in the "Package" column before every "drupal/webform" row
 * Example #5: Then the "Ignored patches" table should list every "All patches" row in the "Package" column before every "drupal/core" row
 *
 */
Then(
  /^the "([^"]*)" table should list every "([^"]*)" row in the "([^"]*)" column before every "([^"]*)" row$/,
  async function (name, first, column, second) {
    const table = await readTable(this.page, name);
    const index = columnIndex(table, column);
    const positionsOf = (value) => table.rows
      .map((row, position) => (row[index] && row[index].text.trim() === value ? position : -1))
      .filter((position) => position !== -1);

    const firsts = positionsOf(first);
    const seconds = positionsOf(second);
    assert.ok(firsts.length > 0, `The "${name}" table has no "${first}" row.\n${render(table)}`);
    assert.ok(seconds.length > 0, `The "${name}" table has no "${second}" row.\n${render(table)}`);
    assert.ok(
      Math.max(...firsts) < Math.min(...seconds),
      `The "${name}" table lists a "${second}" row before a "${first}" row.\n${render(table)}`
    );
  }
);

/**
 * Assert that every package name in a table links to its project page.
 *
 * A drupal/* package links to its drupal.org project, with drupal/core
 * mapping to the "drupal" project; anything else links to Packagist.
 *
 * Example #1: Then every package in the "Patches" table should link to its project page
 * Example #2: Then every package in the "Packages declaring patches" table should link to its project page
 * Example #3: Then every package in the "Ignored patches" table should link to its project page
 * Example #4: Then every package in the "Patches" table should link to its project page
 * Example #5: Then every package in the "Packages declaring patches" table should link to its project page
 *
 */
Then(/^every package in the "([^"]*)" table should link to its project page$/, async function (name) {
  const table = await readTable(this.page, name);
  const packages = allLinks(table).filter((link) => /^[a-z0-9._-]+\/[a-z0-9._-]+$/i.test(link.text));
  assert.ok(packages.length > 0, `The "${name}" table has no package link.\n${render(table)}`);

  packages.forEach((link) => {
    const project = drupalProject(link.text);
    const expected = project === null
      ? `https://packagist.org/packages/${link.text}`
      : `https://www.drupal.org/project/${project}`;
    assert.strictEqual(
      link.href,
      expected,
      `The package "${link.text}" links to ${link.href}, expected ${expected}.`
    );
  });
});

/**
 * Assert that every issue reference in a table links to that drupal.org node.
 *
 * Example #1: Then every issue reference in the "Patches" table should link to its drupal.org issue
 * Example #2: Then every issue reference in the "Ignored patches" table should link to its drupal.org issue
 * Example #3: Then every issue reference in the "Patches" table should link to its drupal.org issue
 * Example #4: Then every issue reference in the "Patches" table should link to its drupal.org issue
 * Example #5: Then every issue reference in the "Ignored patches" table should link to its drupal.org issue
 *
 */
Then(
  /^every issue reference in the "([^"]*)" table should link to its drupal\.org issue$/,
  async function (name) {
    const table = await readTable(this.page, name);
    const issues = allLinks(table).filter((link) => /^#\d{6,8}$/.test(link.text));
    assert.ok(
      issues.length > 0,
      `The "${name}" table has no issue reference link.\n${render(table)}`
    );

    issues.forEach((link) => {
      const expected = `https://www.drupal.org/node/${link.text.slice(1)}`;
      assert.strictEqual(
        link.href,
        expected,
        `The issue reference "${link.text}" links to ${link.href}, expected ${expected}.`
      );
    });
  }
);

/**
 * Assert that every patch file name in a table links to that patch file.
 *
 * Example #1: Then every patch file in the "Patches" table should link to the file it names
 * Example #2: Then every patch file in the "Ignored patches" table should link to the file it names
 * Example #3: Then every patch file in the "Patches" table should link to the file it names
 * Example #4: Then every patch file in the "Patches" table should link to the file it names
 * Example #5: Then every patch file in the "Ignored patches" table should link to the file it names
 *
 */
Then(
  /^every patch file in the "([^"]*)" table should link to the file it names$/,
  async function (name) {
    const table = await readTable(this.page, name);
    const files = allLinks(table).filter((link) => link.text.endsWith('.patch'));
    assert.ok(files.length > 0, `The "${name}" table has no patch file link.\n${render(table)}`);

    files.forEach((link) => {
      assert.ok(
        /^https?:\/\//.test(link.href),
        `The patch file "${link.text}" links to "${link.href}", which is not a patch URL.`
      );
      assert.strictEqual(
        link.href.split('?')[0].split('/').pop(),
        link.text,
        `The patch file "${link.text}" links to ${link.href}, which names a different file.`
      );
    });
  }
);

/**
 * Assert the merge request links of a table, both ways.
 *
 * Every "MR !<id>" link points at that merge request of the patched package's
 * project, and every patch file whose name carries "--mr-<id>" is accompanied
 * by one.
 *
 * Example #1: Then every merge request patch in the "Patches" table should link to its merge request
 * Example #2: Then every merge request patch in the "Ignored patches" table should link to its merge request
 * Example #3: Then every merge request patch in the "Patches" table should link to its merge request
 * Example #4: Then every merge request patch in the "Patches" table should link to its merge request
 * Example #5: Then every merge request patch in the "Ignored patches" table should link to its merge request
 *
 */
Then(
  /^every merge request patch in the "([^"]*)" table should link to its merge request$/,
  async function (name) {
    const table = await readTable(this.page, name);
    const packageOf = (row) => {
      const cell = row[columnIndex(table, 'Package')];
      return cell ? cell.text.trim() : '';
    };

    const mrLinks = allLinks(table).filter((link) => /^MR !\d+$/.test(link.text));
    assert.ok(
      mrLinks.length > 0,
      `The "${name}" table has no merge request link.\n${render(table)}`
    );

    mrLinks.forEach((link) => {
      const id = link.text.replace('MR !', '');
      const project = drupalProject(packageOf(link.row));
      assert.ok(
        project,
        `The merge request link "${link.text}" sits in a row for "${packageOf(link.row)}", which is not a drupal.org project.`
      );
      const expected = `https://git.drupalcode.org/project/${project}/-/merge_requests/${id}`;
      assert.strictEqual(
        link.href,
        expected,
        `The merge request link "${link.text}" points at ${link.href}, expected ${expected}.`
      );
      const file = link.row
        .flatMap((cell) => cell.links)
        .find((sibling) => sibling.text.endsWith('.patch'));
      assert.ok(
        file && file.text.includes(`--mr-${id}`),
        `The merge request link "${link.text}" is not next to a "--mr-${id}" patch file.`
      );
    });

    // And the other way round: a "--mr-<id>" file always gets its link.
    allLinks(table)
      .filter((link) => /--mr-(\d+)/.test(link.text))
      .forEach((file) => {
        const id = file.text.match(/--mr-(\d+)/)[1];
        const sibling = file.row
          .flatMap((cell) => cell.links)
          .find((link) => link.text === `MR !${id}`);
        assert.ok(
          sibling,
          `The patch file "${file.text}" carries merge request ${id} but the row has no "MR !${id}" link.`
        );
      });
  }
);

/**
 * Assert that every link in a table opens safely in a new tab.
 *
 * Example #1: Then every link in the "Patches" table should open in a new tab
 * Example #2: Then every link in the "Packages declaring patches" table should open in a new tab
 * Example #3: Then every link in the "Ignored patches" table should open in a new tab
 * Example #4: Then every link in the "Patches" table should open in a new tab
 * Example #5: Then every link in the "Packages declaring patches" table should open in a new tab
 *
 */
Then(/^every link in the "([^"]*)" table should open in a new tab$/, async function (name) {
  const offenders = await this.page.evaluate((tableName) => {
    const textOf = (node) => (node.textContent || '')
      .replace(/\s+/g, ' ')
      .replace(/\s*\((?:link is external|link sends email)\)/g, '')
      .trim();
    const heading = Array.from(document.querySelectorAll('h3'))
      .find((h) => textOf(h) === tableName);
    let table = null;
    if (heading) {
      table = Array.from(document.querySelectorAll('table')).find((candidate) =>
        heading.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING);
    }
    else {
      const details = Array.from(document.querySelectorAll('details')).find((element) => {
        const summary = element.querySelector('summary');
        return summary && textOf(summary).startsWith(`${tableName} (`);
      });
      table = details ? details.querySelector('table') : null;
    }
    if (!table) {
      return null;
    }
    return Array.from(table.querySelectorAll('a'))
      .filter((a) => a.getAttribute('target') !== '_blank'
        || !(a.getAttribute('rel') || '').includes('noopener'))
      .map((a) => `${textOf(a)} -> target="${a.getAttribute('target')}" rel="${a.getAttribute('rel')}"`);
  }, name);

  assert.ok(offenders, `No table named "${name}" was found on the page.`);
  assert.deepStrictEqual(
    offenders,
    [],
    `These links in the "${name}" table do not open safely in a new tab:\n${offenders.join('\n')}`
  );
});

/**
 * Assert one named link of a table and where it points.
 *
 * Example #1: Then the "Patches" table should contain the link "drupal/core" pointing to "https://www.drupal.org/project/drupal"
 * Example #2: Then the "Packages declaring patches" table should contain the link "webship/patches" pointing to "https://packagist.org/packages/webship/patches"
 * Example #3: Then the "Ignored patches" table should contain the link "drupal/webpatches" pointing to "https://www.drupal.org/project/webpatches"
 * Example #4: Then the "Patches" table should contain the link "#2701575" pointing to "https://www.drupal.org/node/2701575"
 * Example #5: Then the "Patches" table should contain the link "MR !16205" pointing to "https://git.drupalcode.org/project/drupal/-/merge_requests/16205"
 *
 */
Then(
  /^the "([^"]*)" table should contain the link "([^"]*)" pointing to "([^"]*)"$/,
  async function (name, text, href) {
    const table = await readTable(this.page, name);
    const links = allLinks(table).filter((link) => link.text === text);
    assert.ok(
      links.length > 0,
      `The "${name}" table has no link with the text "${text}".\n${render(table)}`
    );
    links.forEach((link) => {
      assert.strictEqual(
        link.href,
        href,
        `The link "${text}" points at ${link.href}, expected ${href}.`
      );
    });
  }
);

/**
 * Assert that a named table is empty and shows the given message.
 *
 * Example #1: Then the "Packages declaring patches" table should be empty and say "No installed package declares patches."
 * Example #2: Then the "Patches" table should be empty and say "No patches are declared for this site."
 * Example #3: Then the "Ignored patches" table should be empty and say "No declared patch is ignored on this site."
 * Example #4: Then the "Packages declaring patches" table should be empty and say "No installed package declares patches."
 * Example #5: Then the "Patches" table should be empty and say "No patches are declared for this site."
 *
 */
Then(
  /^the "([^"]*)" table should be empty and say "([^"]*)"$/,
  async function (name, message) {
    const table = await readTable(this.page, name);
    assert.strictEqual(
      table.rows.length,
      0,
      `The "${name}" table should hold nothing but its empty message.\n${render(table)}`
    );
    assert.strictEqual(
      table.emptyMessage,
      message,
      `The "${name}" table says "${table.emptyMessage}", expected "${message}".`
    );
  }
);

/**
 * Assert whether a report section is expanded or collapsed by default.
 *
 * Example #1: Then the "Patching sources" section should be collapsed
 * Example #2: Then the "Patches" section should be open
 * Example #3: Then the "Ignored patches" section should be open
 * Example #4: Then the "Patching sources" section should be collapsed
 * Example #5: Then the "Patches" section should be open
 *
 */
Then(/^the "([^"]*)" section should be (open|collapsed)$/, async function (title, state) {
  const section = await readSection(this.page, title);
  assert.strictEqual(
    section.open,
    state === 'open',
    `The "${title}" section is ${section.open ? 'open' : 'collapsed'}, expected ${state}.`
  );
});

/**
 * Assert that a section holds the given number of tables.
 *
 * Example #1: Then the "Patching sources" section should hold 2 tables
 * Example #2: Then the "Patches" section should hold 1 table
 * Example #3: Then the "Ignored patches" section should hold 1 table
 * Example #4: Then the "Patching sources" section should hold 2 tables
 * Example #5: Then the "Patches" section should hold 1 table
 *
 */
Then(/^the "([^"]*)" section should hold (\d+) tables?$/, async function (title, count) {
  const section = await readSection(this.page, title);
  assert.strictEqual(
    section.tables,
    Number(count),
    `The "${title}" section holds ${section.tables} tables, expected ${count}.`
  );
});

/**
 * Assert that a section title counts the rows of the table it holds.
 *
 * Example #1: Then the "Patches" section title should count the rows of its table
 * Example #2: Then the "Ignored patches" section title should count the rows of its table
 * Example #3: Then the "Patches" section title should count the rows of its table
 * Example #4: Then the "Ignored patches" section title should count the rows of its table
 * Example #5: Then the "Patches" section title should count the rows of its table
 *
 */
Then(
  /^the "([^"]*)" section title should count the rows of its table$/,
  async function (title) {
    const section = await readSection(this.page, title);
    const table = await readTable(this.page, title);
    const match = section.summary.match(/\((\d+)\)/);
    assert.ok(
      match,
      `The "${title}" section title "${section.summary}" carries no count in brackets.`
    );
    assert.strictEqual(
      Number(match[1]),
      table.rows.length,
      `The "${title}" section title says ${match[1]} but its table has ${table.rows.length} rows.`
    );
  }
);
