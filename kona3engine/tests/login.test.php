<?php
require_once __DIR__ . '/test_common.inc.php';

// login test
test_eq(__LINE__, kona3isLogin(), FALSE, "login test");
test_eq(__LINE__, kona3getHash('abcd', ''), "8131f5dbead7a23afa3a57a7249ab4e6b9ba4f8905ffd07bed0f7d6a92a4730480c6aa939f9c3a333caba7890583fa29e5546c34f35d619238683ccfdee4a6e0", "login hash");
test_eq(__LINE__, kona3getHash('abcd', 'salt#aaa'), "b5bc0d50708f1c90e095494f648d91495c645c20db31d443f80f42c847e5b9afb98c7de4c6489a9563e1523b91f295acbc8f58b9eca84d2461ed3200b5f9ecfa", "login hash with salt");
