use std::net::{SocketAddrV4, Ipv4Addr, TcpListener};
use std::io::Error;
use std::env;

fn main() -> Result<(), Error> {
    let args: Vec<String> = env::args().collect();
    if args.len() != 2 {
        panic!("No port parameter - provide a numeric port");
    }
    let port: u16 = args[1].parse().unwrap();

    let loopback = Ipv4Addr::new(127, 0, 0, 1);
    let socket = SocketAddrV4::new(loopback, port);
    let listener = TcpListener::bind(socket)?;
    let port = listener.local_addr()?;
    println!("Listening on {}, access this port to end the program", port);
    let (_tcp_stream, addr) = listener.accept()?; //block  until requested
    println!("Connection received! {:?} - exiting", addr);
    Ok(())
}
