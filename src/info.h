#ifndef SC2REPLAY_INFO_H
#define SC2REPLAY_INFO_H

#include "types.h"
#include "player.h"

#include <iosfwd>
#include <string>
#include <vector>

namespace sc2replay
{

    // class Players : public std::vector<Player>
    // {
    // public:
    //     Players() : std::vector<Player>() {}
    //     friend std::ostream& operator<<(std::ostream& out, const Players& ps) {
    //         out << ps.size();
    //         return out;
    //     }
            
    // };

    class Info
    {
    public:
        enum GameSpeed
        {
            SLOWER = 0x00,
            SLOW   = 0x01,
            NORMAL = 0x02,
            FAST   = 0x03,
            FASTER = 0x04
        };
  
    public:
        Info();
        ~Info();
  
    public:
        void load(const uint8_t* begin, off_t len) { load(begin, begin+len/sizeof(uint8_t)); }
        void load(const uint8_t* begin, const uint8_t* end);
  
    public:
        const Players& getPlayers() const;
        const uint8_t getNumberOfPlayers() const;
        const std::string& getMapFilename() const;
        const std::string& getMapName() const;
  
    public:
        void exportDump( const std::string& filename ) const;
  
    private:
        template <typename T>
        bool readPlayer(std::basic_istream<T>& s);
    private:
        Players players_;
        std::string mapFilename_;
        std::string mapName_;

    };

} // namespace sc2replay

#endif // SC2REPLAY_INFO_H
// Local Variables:
// mode:c++
// c-file-style: "stroustrup"
// end:

