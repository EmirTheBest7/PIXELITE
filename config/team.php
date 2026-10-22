<?php
declare(strict_types=1);

/**
 * People shown on /team. Add a member = add an entry; the page needs no redesign.
 * Only verified facts: name and role. `role` is a key under lang team.roles.*; `photo` is a path under public/assets/ (null = monogram, never a stock photo);
 * `tags` are keys under team.tags.* (the filter appears once there are at least two different tags); `bio` is a team.bios.<id> key or null.
 */
return [
    ['id' => 'emir-aliev', 'name' => 'Emir Aliev', 'role' => 'founder', 'photo' => null, 'tags' => [], 'bio' => null],
];
